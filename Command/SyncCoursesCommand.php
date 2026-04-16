<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\BlackboardCourseManager;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPICourse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCoursesCommand extends Command
{
    private LearnAPIAuth $learnAPIAuth;
    private LearnAPICourse $learnAPICourse;
    private BlackboardCourseManager $courseManager;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        BlackboardCourseManager $courseManager,
        ?string $name = null
    ) {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->courseManager = $courseManager;

        parent::__construct($name);
    }

    public function configure(): void
    {
        $this
            ->setName('pumukit:blackboard:sync-courses')
            ->setDescription('Fetches all courses from Blackboard Learn and persists them in MongoDB.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
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
