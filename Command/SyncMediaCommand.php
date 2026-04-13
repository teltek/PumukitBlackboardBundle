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
use Pumukit\BlackboardBundle\Services\LearnAPIUser;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMediaCommand extends Command
{
    private LearnAPIAuth $learnAPIAuth;
    private LearnAPICourse $learnAPICourse;
    private LearnAPIUser $learnAPIUser;
    private CollaborateAPIAuth $collaborateAPIAuth;
    private CollaborateAPICourseRecordings $collaborateAPICourseRecordings;
    private CollaborateAPIRecording $collaborateAPIRecording;
    private CollaborateCreateRecording $collaborateCreateRecording;
    private CollaborateAPISessionSearch $collaborateAPISessionSearch;
    private CollaborateAPIUser $collaborateAPIUser;
    private OutputInterface $output;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        LearnAPIUser $learnAPIUser,
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
        $this->learnAPIUser = $learnAPIUser;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateCreateRecording = $collaborateCreateRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;
        $this->collaborateAPIUser = $collaborateAPIUser;

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
        $this->output = $output;
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
        $this->saveRecordings($courseRecordings, $courses, $output, $collaborateToken, $learnToken);

        return Command::SUCCESS;
    }

    private function courseRecordings(array $courses, string $collaborateToken): array
    {
        $courseRecordings = [];
        foreach ($courses as $course) {
            $this->output->writeln('<info>---> Getting recordings for course '.$course.'</info>');
            $courseData = $this->collaborateAPICourseRecordings->getCourseRecordings($collaborateToken, $course);
            if (isset($courseData['results']) && 0 !== count($courseData['results'])) {
                $this->output->writeln('---> Recordings found for course '.$course.': '.count($courseData['results']));
                $courseRecordings[$course] = $courseData['results'];
            } else {
                $this->output->writeln('---> No recordings found for course '.$course);
            }
        }

        return $courseRecordings;
    }

    private function saveRecordings(array $courseRecordings, array $courses, OutputInterface $output, string $collaborateToken, string $learnToken): void
    {
        foreach ($courseRecordings as $key => $recordings) {
            foreach ($recordings as $element) {
                $output->writeln('');
                $output->writeln('<info> ---> STEP 5.1: Getting data from recording '.$element['id'].'</info>');

                if (false === $element['publicLinkAllowed']) {
                    continue;
                }

                $recording = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $element['id']);
                if (!$recording) {
                    $output->writeln('<comment> ---> STEP 5.2: Recording not found</comment>');
                    $output->writeln('');

                    continue;
                }

                try {
                    $downloadUrl = $this->collaborateAPIRecording->generateDownloadURL($recording);
                } catch (\Exception $exception) {
                    $output->writeln('<comment> ---> STEP 5.2: Cannot generate download url</comment>');

                    continue;
                }

                $title = $recording['name'];
                $created = $recording['created'];

                $collaborateRecording = CollaborateRecording::create($element['id'], $key, $courses[$key], $downloadUrl, $element['sessionName'], $title, $created);
                $owners = $this->recordingOwners($element['sessionName'], $collaborateToken, $learnToken);
                $collaborateRecording->addOwners($owners);

                $recording = $this->collaborateCreateRecording->create($collaborateRecording);

                $output->writeln(' ---> STEP 5.2: Saved collaborate recording with ID '.$recording->recording());
            }
        }
    }

    private function recordingOwners(string $sessionName, string $collaborateToken, string $learnToken): array
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
            $collabUser = $this->collaborateAPIUser->searchUser($collaborateToken, $owner);
            $user = $this->learnAPIUser->searchUserById($learnToken, $collabUser['extId']);
            if (!isset($user['contact']['institutionEmail'])) {
                continue;
            }
            $users[$user['contact']['institutionEmail']] = $user['userName'];
        }

        return $users;
    }
}
