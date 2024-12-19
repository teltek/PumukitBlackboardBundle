<?php

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPICourseRecordings;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPISessionSearch;
use Pumukit\BlackboardBundle\Services\CollaborateAPIUser;
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
    private CollaborateCreateRecording $collaborateCreateRecording;
    private CollaborateAPISessionSearch $collaborateAPISessionSearch;
    private CollaborateAPIUser $collaborateAPIUser;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPICourseRecordings $collaborateAPICourseRecordings,
        CollaborateAPIRecording $collaborateAPIRecording,
        CollaborateCreateRecording $collaborateCreateRecording,
        CollaborateAPISessionSearch $collaborateAPISessionSearch,
        CollaborateAPIUser $collaborateAPIUser,
        string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateCreateRecording = $collaborateCreateRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;

        parent::__construct($name);
        $this->collaborateAPIUser = $collaborateAPIUser;
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
        $output->writeln('<info>STEP 1: Connecting to Blackboard Learn API</info>');

        try {
            $learnToken = $this->learnAPIAuth->getToken();
            $output->writeln('DONE');
        } catch (\Exception $exception) {
            $output->writeln('<error>ERROR: '.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>STEP 2: Connecting to Blackboard Collaborate API</info>');

        try {
            $collaborateToken = $this->collaborateAPIAuth->getToken();
            $output->writeln('DONE');
        } catch (\Exception $exception) {
            $output->writeln('<error>ERROR: '.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>STEP 3: Getting list of courses from Blackboard Learn</info>');
        $courses = $this->learnAPICourse->getIdsFromCourses($learnToken);
        $coursesIds = array_keys($courses);
        $output->writeln('Courses found '.count($courses));

        $output->writeln('<info>STEP 4: Getting course recordings from Blackboard Collaborate</info>');
        $courseRecordings = $this->courseRecordings($coursesIds, $collaborateToken);
        $output->writeln('Recordings found '.count($courseRecordings));

        $output->writeln('<info>STEP 5: Save recording data on PuMuKIT</info>');
        $this->saveRecordings($courseRecordings, $courses, $output, $collaborateToken);

        return Command::SUCCESS;
    }

    private function courseRecordings(array $courses, string $collaborateToken): array
    {
        $courseRecordings = [];
        foreach ($courses as $course) {
            $courseData = $this->collaborateAPICourseRecordings->getCourseRecordings($collaborateToken, $course);
            if (isset($courseData['results']) && 0 !== count($courseData['results'])) {
                $courseRecordings[$course] = $courseData['results'];
            }
        }

        return $courseRecordings;
    }

    private function saveRecordings(array $courseRecordings, array $courses, OutputInterface $output, string $collaborateToken): void
    {
        foreach ($courseRecordings as $key => $recordings) {
            foreach ($recordings as $element) {
                $output->writeln('<info> ---> STEP 5.1: Getting data from recording '.$element['id'].'</info>');

                $recording = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $element['id']);
                if (!$recording || !isset($recording['mediaDownloadUrl'])) {
                    $output->writeln('<comment> ---> STEP 5.2: No accesible recording</comment>');
                    $output->writeln('');

                    continue;
                }

                $downloadUrl = $recording['mediaDownloadUrl'];
                $title = $recording['name'];
                $created = $recording['created'];

                $collaborateRecording = CollaborateRecording::create($element['id'], $key, $courses[$key], $downloadUrl, $element['sessionName'], $title, $created);
                $owners = $this->recordingOwners($element['sessionName'], $collaborateToken);
                $collaborateRecording->addOwners($owners);

                $recording = $this->collaborateCreateRecording->create($collaborateRecording);

                $output->writeln(' ---> STEP 5.2: Created new collaborate recording with ID '.$recording->recording());
                $output->writeln('');
            }
        }
    }

    private function recordingOwners(string $sessionName, string $collaborateToken): array
    {
        $sessions = $this->collaborateAPISessionSearch->searchSessions($collaborateToken);

        $sessionsResults = array_column($sessions['results'], 'name');
        $index = array_search($sessionName, $sessionsResults);
        $sessionId = $sessions['results'][$index]['id'];

        $enrollments = $this->collaborateAPISessionSearch->getEnrollmentsBySessionId($collaborateToken, $sessionId);

        $owners = [];
        foreach ($enrollments['results'] as $enrollment) {
            if ('moderator' === $enrollment['launchingRole']) {
                $owners[] = $enrollment['userId'];
            }
        }

        $users = [];
        foreach ($owners as $owner) {
            $user = $this->collaborateAPIUser->searchUser($collaborateToken, $owner);
            $users[$user['email']] = $user['displayName'];
        }

        return $users;
    }
}
