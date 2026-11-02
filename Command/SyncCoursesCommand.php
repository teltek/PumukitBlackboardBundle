<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\BlackboardCourseManager;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPICourse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pumukit:blackboard:sync-courses', description: 'Fetches all courses from Blackboard Learn and persists them in MongoDB.')]
class SyncCoursesCommand extends Command
{
    public function __construct(
        private readonly LearnAPIAuth $learnAPIAuth,
        private readonly LearnAPICourse $learnAPICourse,
        private readonly BlackboardCourseManager $courseManager,
        private readonly bool $isEnabled,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isEnabled) {
            $output->writeln('<comment>The Blackboard bundle is disabled (pumukit_blackboard.enable: false).</comment>');

            return Command::FAILURE;
        }

        $output->writeln('<info>STEP 1: Connecting to Blackboard Learn API</info>');

        try {
            $learnToken = $this->learnAPIAuth->getToken();
            $output->writeln('Connected.');
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>STEP 2: Fetching courses (sorted by modified desc)</info>');

        try {
            $courses = $this->learnAPICourse->getCourses($learnToken);
        } catch (\Exception $e) {
            $output->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $total = count($courses);
        $output->writeln(sprintf('Courses found: %d', $total));
        $output->writeln('<info>STEP 3: Upserting courses in MongoDB</info>');

        $batchSize = 50;

        foreach ($courses as $i => $course) {
            $this->courseManager->upsert($course['learnId'], $course['collaborateId'], $course['name']);

            $output->writeln(sprintf(
                ' ---> [%d/%d] %s (%s)',
                $i + 1,
                $total,
                $course['name'],
                $course['collaborateId']
            ));

            if (0 === (($i + 1) % $batchSize)) {
                $this->courseManager->flush();
            }
        }

        $this->courseManager->flush();

        $output->writeln(sprintf('<info>Done. %d courses processed.</info>', $total));

        return Command::SUCCESS;
    }
}
