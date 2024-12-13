<?php

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPICourseRecordings;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPISessionSearch;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPICourse;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMediaCommand extends Command
{
    private LearnAPIAuth $learnAPIAuth;
    private LearnAPICourse $learnAPICourse;
    private CollaborateAPIAuth $collaborateAPIAuth;
    private CollaborateAPICourseRecordings $collaborateAPICourseRecordings;
    private CollaborateAPIRecording $collaborateAPIRecording;
    private CollaborateAPISessionSearch $collaborateAPISessionSearch;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPICourseRecordings $collaborateAPICourseRecordings,
        CollaborateAPIRecording $collaborateAPIRecording,
        CollaborateAPISessionSearch $collaborateAPISessionSearch,
        string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;

        parent::__construct($name);
    }

    public function configure(): void
    {
        $this
            ->setName('pumukit:blackboard:sync')
            ->setDescription('This command download blackboard collaborate recordings')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>***** Getting learn token to use api *****</info>');
        $learnToken = $this->learnAPIAuth->getToken();

        $output->writeln('<info>***** Getting collaborate token to use api *****</info>');
        $collaborateToken = $this->collaborateAPIAuth->getToken();

        $output->writeln('<info>***** Getting list of courses *****</info>');
        $courses = $this->learnAPICourse->getIdsFromCourses($learnToken);
        $output->writeln('<info>[COLLABORATE] Courses found '. count($courses) .'</info>');

        $courseRecordings = [];
        $output->writeln('***** Getting recordings from course *****');
        foreach ($courses as $course) {
            $courseData = $this->collaborateAPICourseRecordings->getCourseRecordings($collaborateToken, $course);
            if(isset($courseData['results']) && 0 !== count($courseData['results'])) {
                $courseRecordings[$course] = $courseData['results'];
            }
        }

        foreach ($courseRecordings as $key => $recordings) {
            foreach ($recordings as $element) {
                $output->writeln('<info>***** Getting data from recording '. $element['id'] .'</info>');

                $recording = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $element['id']);
                if(!$recording || !isset($recording['mediaDownloadUrl'])) {
                    continue;
                }

                $downloadUrl = $recording['mediaDownloadUrl'];
                $session = $this->collaborateAPISessionSearch->searchBySessionName($collaborateToken, $element['sessionName']);

                //$enrollments = $this->collaborateAPISessionSearch->getEnrollmentsBySessionId($collaborateToken, '4d0d37cb688f431e973b6ac19ff10599');
                $enrollments = $this->collaborateAPISessionSearch->getEnrollmentsBySessionId($collaborateToken, '2f74e38cb29042a4af2da9d9c8398caa');
                var_dump($enrollments);die;
                $collaborateRecording = CollaborateRecording::create($element['id'], $key, $element['sessionName'], $downloadUrl);
            }
        }

        die;

        $sessions = [];
        foreach ($recordingSession as $key => $element) {
            $session = $this->collaborateAPISessionSearch->searchBySessionName($collaborateToken, $element);
            if(!isset($session['results'][0])) {
                continue;
            }
            $sessions[] = $this->collaborateAPISessionSearch->getEnrollmentsBySessionId($collaborateToken, $session['results'][0]['id']);
        }

        $users = [];
        foreach ($sessions as $session) {
            if(!isset($session['results'])) {
                continue;
            }

            foreach($session['results'] as $sessionResult) {
                if($sessionResult['launchingRole'] === 'moderator') {
                    $users[] = $sessionResult['userId'];
                }
            }
        }

        return Command::SUCCESS;
    }
}
