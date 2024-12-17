<?php

namespace Pumukit\BlackboardBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\BlackboardBundle\Document\CollaborateRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPICourseRecordings;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPISessionSearch;
use Pumukit\BlackboardBundle\Services\CollaborateCreateRecording;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPICourse;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\ValueObject\Path;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportRecordingsCommand extends Command
{
    public const DEFAULT_TMP_PATH = '';

    private LearnAPIAuth $learnAPIAuth;
    private LearnAPICourse $learnAPICourse;
    private CollaborateAPIAuth $collaborateAPIAuth;
    private CollaborateAPICourseRecordings $collaborateAPICourseRecordings;
    private CollaborateAPIRecording $collaborateAPIRecording;
    private CollaborateAPISessionSearch $collaborateAPISessionSearch;
    private CollaborateCreateRecording $collaborateCreateRecording;
    private DocumentManager $documentManager;
    private HttpClientInterface $httpClient;
    private FactoryService $factoryService;
    private JobCreator $jobCreator;
    private i18nService $i18nService;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPICourseRecordings $collaborateAPICourseRecordings,
        CollaborateAPIRecording $collaborateAPIRecording,
        CollaborateAPISessionSearch $collaborateAPISessionSearch,
        CollaborateCreateRecording $collaborateCreateRecording,
        DocumentManager $documentManager,
        HttpClientInterface $httpClient,
        FactoryService $factoryService,
        JobCreator $jobCreator,
        i18nService $i18nService,
        string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;
        $this->collaborateCreateRecording = $collaborateCreateRecording;
        $this->documentManager = $documentManager;
        $this->httpClient = $httpClient;
        $this->factoryService = $factoryService;
        $this->jobCreator = $jobCreator;
        $this->i18nService = $i18nService;

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
        $output->writeln('<info>***** Getting collaborate token to use api *****</info>');
        $collaborateToken = $this->collaborateAPIAuth->getToken();

        $recordings = $this->getRecordingsToImport();

        foreach ($recordings as $recording) {
            $pathFile = $this->downloadRecording($recording);
            $series = $this->getSeries();
            $multimediaObject = $this->factoryService->createMultimediaObject($series);
            $i18nTitle = $this->i18nService->generateI18nText($recording->title());
            $multimediaObject->setI18nTitle($i18nTitle);
            $multimediaObject->setRecordDate($recording->created());
            $jobOptions = new JobOptions('master_copy', 2, 'en', [], []);
            $this->jobCreator->fromPath($multimediaObject, $pathFile, $jobOptions);
            $recording->markAsImported();
            $this->documentManager->flush();
        }

        return Command::SUCCESS;
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

        file_put_contents(self::DEFAULT_TMP_PATH.$extension, $fileContent);

        return Path::create(self::DEFAULT_TMP_PATH.$extension);
    }

    private function getSeries(): Series
    {
        return $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => new ObjectId('67613c953788ffd1da05c983')]);
    }
}
