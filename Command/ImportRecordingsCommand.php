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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportRecordingsCommand extends Command
{
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
        string $name = null
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
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>STEP 1: Getting recordings to import</info>');
        $recordings = $this->getRecordingsToImport();

        $output->writeln('<info>STEP 2: Validate recordings to import</info>');
        foreach ($recordings as $recording) {
            $users = $this->isValidToImport($recording);
            $output->writeln(' ---> STEP 2.1: Getting recording '.$recording->recording());
            if (empty($users)) {
                $output->writeln(' ---> STEP 2.2:[WARNING] Recording with ID '.$recording->recording().' doesnt have owners on PuMuKIT');

                continue;
            }

            $pathFile = $this->downloadRecording($recording);
            $series = $this->getSeries($recording);
            $multimediaObject = $this->factoryService->createMultimediaObject($series);
            $i18nTitle = $this->i18nService->generateI18nText($recording->title());
            $multimediaObject->setI18nTitle($i18nTitle);
            $multimediaObject->setRecordDate($recording->created());
            $jobOptions = new JobOptions('master_copy', 2, 'en', [], []);
            $this->jobCreator->fromPath($multimediaObject, $pathFile, $jobOptions);
            $multimediaObject->setProperty('blackboard_recording', $recording->recording());
            $recording->markAsImported();

            $role = $this->getOwnerRole();
            foreach ($users as $user) {
                $multimediaObject->addPersonWithRole($user->getPerson(), $role);
            }

            $this->documentManager->flush();

            $output->writeln(' ---> STEP 2.2: Recording with ID '.$recording->recording().' imported');
        }

        return Command::SUCCESS;
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

    private function getRecordingsToImport(): array
    {
        return $this->documentManager->getRepository(CollaborateRecording::class)->findBy([
            'imported' => false,
        ]);
    }

    private function downloadRecording(CollaborateRecording $recording): ?Path
    {
        $response = $this->httpClient->request('GET', $recording->downloadUrl());
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
        }

        $fileContent = $response->getContent();

        $data = parse_url($recording->downloadUrl());
        $extension = explode('.', $data['path']);
        $extension = end($extension);

        file_put_contents($this->tmpPath.'/'.$recording->recording().'.'.$extension, $fileContent);

        return Path::create($this->tmpPath.'/'.$recording->recording().'.'.$extension);
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
