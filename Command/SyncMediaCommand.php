<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Document\BlackboardCourse;
use Pumukit\BlackboardBundle\Services\BlackboardCourseManager;
use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPICourseRecordings;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPISessionSearch;
use Pumukit\BlackboardBundle\Services\CollaborateAPIUser;
use Pumukit\BlackboardBundle\Services\CollaborateCreateRecording;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPIUser;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMediaCommand extends Command
{
    private const DEFAULT_LIMIT = 50;
    private const SLEEP_MS = 200; // milliseconds between Collaborate requests

    private LearnAPIAuth $learnAPIAuth;
    private LearnAPIUser $learnAPIUser;
    private CollaborateAPIAuth $collaborateAPIAuth;
    private CollaborateAPICourseRecordings $collaborateAPICourseRecordings;
    private CollaborateAPIRecording $collaborateAPIRecording;
    private CollaborateCreateRecording $collaborateCreateRecording;
    private CollaborateAPISessionSearch $collaborateAPISessionSearch;
    private CollaborateAPIUser $collaborateAPIUser;
    private BlackboardCourseManager $courseManager;
    private OutputInterface $output;
    private array $errors = [];

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPIUser $learnAPIUser,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPICourseRecordings $collaborateAPICourseRecordings,
        CollaborateAPIRecording $collaborateAPIRecording,
        CollaborateCreateRecording $collaborateCreateRecording,
        CollaborateAPISessionSearch $collaborateAPISessionSearch,
        CollaborateAPIUser $collaborateAPIUser,
        BlackboardCourseManager $courseManager,
        ?string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPIUser = $learnAPIUser;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPICourseRecordings = $collaborateAPICourseRecordings;
        $this->collaborateAPIRecording = $collaborateAPIRecording;
        $this->collaborateCreateRecording = $collaborateCreateRecording;
        $this->collaborateAPISessionSearch = $collaborateAPISessionSearch;
        $this->collaborateAPIUser = $collaborateAPIUser;
        $this->courseManager = $courseManager;

        parent::__construct($name);
    }

    public function configure(): void
    {
        $this
            ->setName('pumukit:blackboard:sync')
            ->setDescription(
                'Step 2 — Fetches Collaborate recordings for courses in status=pending_recordings '.
                'and saves them in PuMuKIT. Run pumukit:blackboard:sync-courses first.'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of courses to process in this run.',
                self::DEFAULT_LIMIT
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $limit = (int) $input->getOption('limit');

        $output->writeln('<info>STEP 1: Connecting to Blackboard Learn API</info>');
        try {
            $learnToken = $this->learnAPIAuth->getToken();
            $output->writeln('Connected.');
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>STEP 2: Connecting to Blackboard Collaborate API</info>');
        try {
            $collaborateToken = $this->collaborateAPIAuth->getToken();
            $output->writeln('Connected.');
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>STEP 3: Loading up to %d courses with status=pending_recordings</info>', $limit));
        $courses = $this->courseManager->findPendingRecordings($limit);
        $total = count($courses);
        $output->writeln(sprintf('Courses to process: %d', $total));

        if (0 === $total) {
            $output->writeln('<comment>No pending courses. Run pumukit:blackboard:sync-courses first.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln('<info>STEP 4: Fetching recordings and saving to PuMuKIT</info>');

        foreach ($courses as $index => $course) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<info>[%d/%d] Course: %s (%s)</info>',
                $index + 1,
                $total,
                $course->name(),
                $course->collaborateId()
            ));

            try {
                $this->processCourse($course, $collaborateToken, $learnToken, $output);
            } catch (\Exception $e) {
                $this->courseManager->markAsError($course, $e->getMessage());
                $output->writeln('<error>ERROR: '.$e->getMessage().' — course marked as error.</error>');
            }

            // Rate limiting: small pause between Collaborate requests
            usleep(self::SLEEP_MS * 1000);
        }

        if (!empty($this->errors)) {
            $output->writeln('');
            $output->writeln('<comment>===== ERRORS FOUND DURING EXECUTION =====</comment>');
            foreach ($this->errors as $error) {
                $output->writeln('<error>'.$error.'</error>');
            }
            $output->writeln('<comment>Total errors: '.count($this->errors).'</comment>');
        }

        return Command::SUCCESS;
    }

    private function processCourse(BlackboardCourse $course, string $collaborateToken, string $learnToken, OutputInterface $output): void
    {
        $courseData = $this->collaborateAPICourseRecordings->getCourseRecordings($collaborateToken, $course->collaborateId());

        if (empty($courseData['results'])) {
            $output->writeln(' ---> No recordings found. Marking as done.');
            $this->courseManager->markAsDone($course);

            return;
        }

        $recordings = $courseData['results'];
        $output->writeln(sprintf(' ---> %d recording(s) found.', count($recordings)));

        foreach ($recordings as $element) {
            $output->writeln(sprintf(' ---> Processing recording %s', $element['id']));

            if ($this->collaborateCreateRecording->alreadyExists($element['id'])) {
                $output->writeln('<comment> ---> Already exists, skipping.</comment>');

                continue;
            }

            if (false === $element['publicLinkAllowed']) {
                $output->writeln('<comment> ---> publicLinkAllowed=false, skipping.</comment>');

                continue;
            }

            $recordingData = $this->collaborateAPIRecording->getRecordingData($collaborateToken, $element['id']);
            if (!$recordingData) {
                $output->writeln('<comment> ---> Recording data not found, skipping.</comment>');

                continue;
            }

            try {
                $downloadUrl = $this->collaborateAPIRecording->generateDownloadURL($recordingData);
            } catch (\Exception $e) {
                $output->writeln('<comment> ---> Cannot generate download URL, skipping.</comment>');

                continue;
            }

            $collaborateRecording = CollaborateRecording::create(
                $element['id'],
                $course->collaborateId(),
                $course->name(),
                $downloadUrl,
                $element['sessionName'],
                $recordingData['name'],
                $recordingData['created']
            );

            $owners = $this->recordingOwners($element['sessionName'], $collaborateToken, $learnToken);
            $collaborateRecording->addOwners($owners);

            $saved = $this->collaborateCreateRecording->create($collaborateRecording);
            $output->writeln(sprintf(' ---> Saved with ID %s', $saved->recording()));
        }

        $this->courseManager->markAsPendingImport($course, count($recordings));
        $output->writeln(sprintf(' ---> Course marked as pending_import (%d recordings).', count($recordings)));
    }

    private function recordingOwners(string $sessionName, string $collaborateToken, string $learnToken): array
    {
        $sessions = $this->collaborateAPISessionSearch->searchSessions($collaborateToken);

        $sessionsResults = array_column($sessions['results'], 'name');
        $index = array_search($sessionName, $sessionsResults);

        if (false === $index || !isset($sessions['results'][$index]['id'])) {
            $this->errors[] = 'Session not found for name: "'.$sessionName.'"';
            $this->output->writeln('<comment> ---> WARNING: Session not found for name "'.$sessionName.'", skipping owners.</comment>');

            return [];
        }

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
            try {
                $collabUser = $this->collaborateAPIUser->searchUser($collaborateToken, $owner);
                $user = $this->learnAPIUser->searchUserById($learnToken, $collabUser['extId']);
            } catch (\Exception $e) {
                $this->errors[] = 'Error getting user data for owner "'.$owner.'": '.$e->getMessage();
                $this->output->writeln('<comment> ---> WARNING: Skipping owner "'.$owner.'": '.$e->getMessage().'</comment>');

                continue;
            }

            if (!isset($user['contact']['institutionEmail'])) {
                continue;
            }
            $users[$user['contact']['institutionEmail']] = $user['userName'];
        }

        return $users;
    }
}
