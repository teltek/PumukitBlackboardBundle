<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BlackboardBundle\Document\CollaborateRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Document\ValueObject\Path;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AsCommand(name: 'pumukit:blackboard:import:recordings', description: 'This command import blackboard collaborate recordings on PuMuKIT')]
class ImportRecordingsCommand extends Command
{
    private const BATCH_SIZE = 10;

    private bool $fallbackFullDownload = false;

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly HttpClientInterface $httpClient,
        private readonly FactoryService $factoryService,
        private readonly JobCreator $jobCreator,
        private readonly i18nService $i18nService,
        private readonly PersonService $personService,
        private readonly CollaborateAPIAuth $collaborateAPIAuth,
        private readonly CollaborateAPIRecording $collaborateAPIRecording,
        private readonly string $tmpPath,
        private readonly bool $isEnabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'fallback-full-download',
                null,
                InputOption::VALUE_NONE,
                'If streaming (chunked) download fails, retry downloading the full file into memory as a fallback.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isEnabled) {
            $output->writeln('<comment>The Blackboard bundle is disabled (pumukit_blackboard.enable: false).</comment>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Starting import of Blackboard recordings</info>');

        $this->fallbackFullDownload = $input->getOption('fallback-full-download');
        if ($this->fallbackFullDownload) {
            $output->writeln('<comment>Fallback full-download mode is enabled.</comment>');
        }

        try {
            $this->collaborateAPIAuth->getToken();
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not authenticate with Collaborate API: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $countImported = 0;
        $countSkipped = 0;
        $countErrors = 0;
        $processed = 0;

        foreach ($this->getRecordingsIterator() as $recording) {
            $recordingId = $recording->recording();
            $output->writeln(sprintf('<info>[%s] Processing recording...</info>', $recordingId));

            $cleared = false;
            $pathFile = null;
            $multimediaObject = null;
            $jobCreated = false;

            try {
                if ($this->alreadyImported($recordingId)) {
                    $recording->markAsImported();
                    $this->documentManager->flush();
                    $output->writeln(sprintf(' ---> [WARNING] Recording %s was already imported. Marking it.', $recordingId));
                    ++$countSkipped;

                    continue;
                }

                $users = $this->isValidToImport($recording);
                if (empty($users)) {
                    $output->writeln(sprintf(' ---> [WARNING] Recording %s has no owners with a person on PuMuKIT. Skipping.', $recordingId));
                    ++$countSkipped;

                    continue;
                }

                $pathFile = $this->downloadRecording($recording, $this->collaborateAPIAuth->getToken());

                $series = $this->getSeries($recording);
                $multimediaObject = $this->factoryService->createMultimediaObject($series, true, reset($users));
                $multimediaObject->setI18nTitle($this->i18nService->generateI18nText($recording->title()));
                $multimediaObject->setRecordDate($recording->created());
                $multimediaObject->setProperty('blackboard_recording', $recordingId);

                $role = $this->getOwnerRole();
                foreach ($users as $user) {
                    if (!$multimediaObject->containsPersonWithRole($user->getPerson(), $role)) {
                        $this->personService->createRelationPerson($user->getPerson(), $role, $multimediaObject, false, false);
                    }
                }

                $this->jobCreator->fromPath($multimediaObject, $pathFile, new JobOptions('master_copy', 2, 'en', [], []));
                $jobCreated = true;
                $recording->markAsImported();
                $this->documentManager->flush();
                ++$countImported;
                $output->writeln(sprintf(' ---> [OK] Recording %s imported successfully.', $recordingId));
            } catch (\Throwable $e) {
                ++$countErrors;
                $output->writeln(sprintf(
                    ' ---> [ERROR] Failed to import recording %s: %s',
                    $recordingId,
                    $e->getMessage()
                ));
                if (!$jobCreated) {
                    $this->discard($multimediaObject, $pathFile, $output);
                }
                $this->documentManager->clear();
                $cleared = true;
            }

            ++$processed;
            if (!$cleared && 0 === ($processed % self::BATCH_SIZE)) {
                $this->documentManager->clear();
                $output->writeln(sprintf('<comment>DocumentManager cleared after %d recordings</comment>', $processed));
            }
        }

        $output->writeln(sprintf(
            '<info>Import finished — Imported: %d | Skipped: %d | Errors: %d</info>',
            $countImported,
            $countSkipped,
            $countErrors
        ));

        return $countErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function isValidToImport(CollaborateRecording $recording): array
    {
        $users = [];
        foreach (array_keys($recording->owners()) as $email) {
            $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user instanceof User && $user->getPerson() instanceof Person) {
                $users[] = $user;
            }
        }

        return $users;
    }

    private function alreadyImported(string $recordingId): bool
    {
        return null !== $this->documentManager->getRepository(MultimediaObject::class)->findOneBy(['properties.blackboard_recording' => $recordingId]);
    }

    private function discard(?MultimediaObject $multimediaObject, ?Path $pathFile, OutputInterface $output): void
    {
        try {
            if ($multimediaObject?->getId()) {
                $this->factoryService->deleteMultimediaObject($multimediaObject);
            }
        } catch (\Throwable $e) {
            $output->writeln(sprintf(' ---> [ERROR] Could not delete the incomplete multimedia object: %s', $e->getMessage()));
        }

        if ($pathFile && is_file($pathFile->path())) {
            unlink($pathFile->path());
        }
    }

    private function getRecordingsIterator(): iterable
    {
        return $this->documentManager->getRepository(CollaborateRecording::class)
            ->createQueryBuilder()
            ->field('imported')->equals(false)
            ->getQuery()
            ->execute()
        ;
    }

    private function downloadRecording(CollaborateRecording $recording, string $collaborateToken): Path
    {
        $recordingData = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $recording->recording());
        if (!$recordingData) {
            throw new \RuntimeException('Could not retrieve recording data from Collaborate API.');
        }

        $freshUrl = $this->collaborateAPIRecording->generateDownloadURL($recordingData);

        $data = parse_url($freshUrl);
        $extension = pathinfo($data['path'] ?? '', PATHINFO_EXTENSION);
        $filePath = $this->tmpPath.'/'.$recording->recording().($extension ? '.'.$extension : '');

        $response = $this->httpClient->request('GET', $freshUrl);
        $statusCode = $response->getStatusCode();
        if (Response::HTTP_OK !== $statusCode) {
            throw new \RuntimeException(sprintf('Download URL returned HTTP %d.', $statusCode));
        }

        try {
            return $this->streamToFile($response, $filePath);
        } catch (\Throwable $streamException) {
            if (!$this->fallbackFullDownload) {
                throw $streamException;
            }

            return $this->fullDownloadToFile($freshUrl, $filePath);
        }
    }

    /**
     * Downloads the response body by streaming chunks directly to disk.
     *
     * Each chunk from Symfony's HttpClient can be:
     *   - isFirst(): headers ready, getContent() always returns "" → the empty-content guard skips it safely.
     *   - isLast():  end of stream, may or may not carry body data → written if non-empty, then loop ends.
     *   - otherwise: regular body data → written to disk.
     *
     * Relying on the `'' !== $content` guard (rather than an explicit isFirst() skip) ensures
     * that no byte of actual content is ever missed, regardless of chunk type.
     */
    private function streamToFile(ResponseInterface $response, string $filePath): Path
    {
        $fileHandle = fopen($filePath, 'w');
        if (false === $fileHandle) {
            throw new \RuntimeException(sprintf('Cannot open file for writing: %s', $filePath));
        }

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content = $chunk->getContent();
                if ('' !== $content && false === fwrite($fileHandle, $content)) {
                    throw new \RuntimeException(sprintf('Failed to write to file: %s', $filePath));
                }

                if ($chunk->isLast()) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            fclose($fileHandle);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            throw $e;
        }

        fclose($fileHandle);

        return Path::create($filePath);
    }

    private function fullDownloadToFile(string $url, string $filePath): Path
    {
        $response = $this->httpClient->request('GET', $url);
        $statusCode = $response->getStatusCode();
        if (Response::HTTP_OK !== $statusCode) {
            throw new \RuntimeException(sprintf('Fallback download URL returned HTTP %d.', $statusCode));
        }

        $content = $response->getContent();
        if (false === file_put_contents($filePath, $content)) {
            throw new \RuntimeException(sprintf('Failed to write file: %s', $filePath));
        }

        return Path::create($filePath);
    }

    private function getSeries(CollaborateRecording $recording): Series
    {
        $series = $this->documentManager->getRepository(Series::class)->findOneBy([
            'properties.blackboard_course' => $recording->courseUUID(),
        ]);

        if (!$series) {
            $title = $this->i18nService->generateI18nText('Blackboard course: '.$recording->courseName());
            $series = $this->factoryService->createSeries(null, $title);
            $series->setProperty('blackboard_course', $recording->courseUUID());
            $this->documentManager->flush();
        }

        return $series;
    }

    private function getOwnerRole(): Role
    {
        $role = $this->personService->getPersonalScopeRole();
        if (!$role instanceof Role) {
            throw new \RuntimeException('The owner role does not exist');
        }

        return $role;
    }
}
