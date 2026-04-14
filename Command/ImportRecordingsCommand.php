<?php

namespace Pumukit\BlackboardBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BlackboardBundle\Document\CollaborateRecording;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Document\ValueObject\Path;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ImportRecordingsCommand extends Command
{
    private const BATCH_SIZE = 10;

    private bool $fallbackFullDownload = false;

    private DocumentManager $documentManager;
    private HttpClientInterface $httpClient;
    private FactoryService $factoryService;
    private JobCreator $jobCreator;
    private i18nService $i18nService;
    private UserService $userService;
    private $tmpPath;

    public function __construct(
        DocumentManager $documentManager,
        HttpClientInterface $httpClient,
        FactoryService $factoryService,
        JobCreator $jobCreator,
        i18nService $i18nService,
        UserService $userService,
        string $tmpPath,
        ?string $name = null
    ) {
        $this->documentManager = $documentManager;
        $this->httpClient = $httpClient;
        $this->factoryService = $factoryService;
        $this->jobCreator = $jobCreator;
        $this->i18nService = $i18nService;
        $this->userService = $userService;
        $this->tmpPath = $tmpPath;

        parent::__construct($name);
    }

    public function configure(): void
    {
        $this
            ->setName('pumukit:blackboard:import:recordings')
            ->setDescription('This command import blackboard collaborate recordings on PuMuKIT')
            ->addOption(
                'fallback-full-download',
                null,
                InputOption::VALUE_NONE,
                'If streaming (chunked) download fails, retry downloading the full file into memory as a fallback.'
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting import of Blackboard recordings</info>');

        $this->fallbackFullDownload = $input->getOption('fallback-full-download');
        if ($this->fallbackFullDownload) {
            $output->writeln('<comment>Fallback full-download mode is enabled.</comment>');
        }

        $countImported = 0;
        $countSkipped = 0;
        $countErrors = 0;
        $processed = 0;

        foreach ($this->getRecordingsIterator() as $recording) {
            $recordingId = $recording->recording();
            $output->writeln(sprintf('<info>[%s] Processing recording...</info>', $recordingId));

            $cleared = false;

            try {
                $users = $this->isValidToImport($recording);
                if (empty($users)) {
                    $output->writeln(sprintf(' ---> [WARNING] Recording %s has no owners on PuMuKIT. Skipping.', $recordingId));
                    ++$countSkipped;

                    continue;
                }

                $pathFile = $this->downloadRecording($recording);
                if (null === $pathFile) {
                    $output->writeln(sprintf(' ---> [ERROR] Could not download recording %s. Skipping.', $recordingId));
                    ++$countErrors;

                    continue;
                }

                $series = $this->getSeries($recording);
                $multimediaObject = $this->factoryService->createMultimediaObject($series, true, reset($users));
                $i18nTitle = $this->i18nService->generateI18nText($recording->title());
                $multimediaObject->setI18nTitle($i18nTitle);
                $multimediaObject->setRecordDate($recording->created());
                $jobOptions = new JobOptions('master_copy', 2, 'en', [], []);
                $this->jobCreator->fromPath($multimediaObject, $pathFile, $jobOptions);
                $multimediaObject->setProperty('blackboard_recording', $recordingId);
                $recording->markAsImported();

                $role = $this->getOwnerRole();
                foreach ($users as $user) {
                    $multimediaObject->addPersonWithRole($user->getPerson(), $role);
                }

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
        $owners = $recording->owners();
        $users = [];
        foreach ($owners as $key => $owner) {
            $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $key]);
            if ($user) {
                $users[] = $user;
            }
        }

        return $users;
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

    /**
     * Streams the remote file to disk chunk by chunk to avoid loading large files into memory.
     *
     * The Symfony HttpClient stream yields three kinds of chunks:
     *   - isFirst(): only headers, no body content → skip it.
     *   - isLast():  signals end of stream, may or may not carry content → write + stop.
     *   - otherwise: regular body data → write to disk.
     *
     * If streaming fails and --fallback-full-download is enabled, the full file is downloaded
     * into memory as a last resort.
     */
    private function downloadRecording(CollaborateRecording $recording): ?Path
    {
        $data = parse_url($recording->downloadUrl());
        $extension = pathinfo($data['path'] ?? '', PATHINFO_EXTENSION);
        $filePath = $this->tmpPath.'/'.$recording->recording().($extension ? '.'.$extension : '');

        $response = $this->httpClient->request('GET', $recording->downloadUrl());
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
        }

        try {
            return $this->streamToFile($response, $filePath);
        } catch (\Throwable $streamException) {
            if (!$this->fallbackFullDownload) {
                throw $streamException;
            }

            // Streaming failed — retry with a full in-memory download
            return $this->fullDownloadToFile($recording, $filePath);
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

    /**
     * Fallback: downloads the full response body into memory and writes it to disk in one shot.
     * Only used when streaming fails and --fallback-full-download is enabled.
     */
    private function fullDownloadToFile(CollaborateRecording $recording, string $filePath): ?Path
    {
        $response = $this->httpClient->request('GET', $recording->downloadUrl());
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
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
        return $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => 'owner']);
    }
}
