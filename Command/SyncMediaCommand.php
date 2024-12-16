<?php

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPICourseRecordings;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPISessionSearch;
use Pumukit\BlackboardBundle\Services\CollaborateCreateRecording;
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
    private CollaborateCreateRecording $collaborateCreateRecording;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPICourseRecordings $collaborateAPICourseRecordings,
        CollaborateAPIRecording $collaborateAPIRecording,
        CollaborateAPISessionSearch $collaborateAPISessionSearch,
        CollaborateCreateRecording $collaborateCreateRecording,
        string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;
        $this->collaborateCreateRecording = $collaborateCreateRecording;

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
        $output->writeln('<info>[COLLABORATE] Courses found '.count($courses).'</info>');

        $courseRecordings = [];
        $output->writeln('***** Getting recordings from course *****');
        foreach ($courses as $course) {
            $courseData = $this->collaborateAPICourseRecordings->getCourseRecordings($collaborateToken, $course);
            if (isset($courseData['results']) && 0 !== count($courseData['results'])) {
                $courseRecordings[$course] = $courseData['results'];
            }
        }

        foreach ($courseRecordings as $key => $recordings) {
            foreach ($recordings as $element) {
                $output->writeln('<info>***** Getting data from recording '.$element['id'].'</info>');

                $recording = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $element['id']);
                if (!$recording || !isset($recording['mediaDownloadUrl'])) {
                    continue;
                }

                $downloadUrl = $recording['mediaDownloadUrl'];

                //                TODO: Obtener usuarios moderadores del curso. Crear serie del curso y agregar los moderadores. Autoprovisionar los usuarios.
                //                TODO: Get info about moderator to assing on PuMuKIT.
                //                $sessions = $this->collaborateAPISessionSearch->searchSessions($collaborateToken);
                //
                //                $sessionsNames = array_column($sessions['results'], 'name');
                //                $index = array_search($element['sessionName'], $sessionsNames);
                //                $sessionId = $sessions['results'][$index]['id'];
                //
                //                $enrollments = $this->collaborateAPISessionSearch->getEnrollmentsBySessionId($collaborateToken, $sessionId);

                $collaborateRecording = CollaborateRecording::create($element['id'], $key, $downloadUrl, $element['sessionName']);
                $recording = $this->collaborateCreateRecording->create($collaborateRecording);

                $output->writeln('<info>Created new collaborate recording with ID '.$recording->recording().'</info>');
            }
        }

        return Command::SUCCESS;
    }
}
