<?php

namespace Pumukit\BlackboardBundle\Command;

use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecordings;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPICourse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncMediaCommand extends Command
{
    private LearnAPIAuth $learnAPIAuth;
    private LearnAPICourse $learnAPICourse;
    private CollaborateAPIAuth $collaborateAPIAuth;
    private CollaborateAPIRecordings $collaborateAPIRecordings;

    public function __construct(
        LearnAPIAuth $learnAPIAuth,
        LearnAPICourse $learnAPICourse,
        CollaborateAPIAuth $collaborateAPIAuth,
        CollaborateAPIRecordings $collaborateAPIRecordings,
        string $name = null
    )
    {
        $this->learnAPIAuth = $learnAPIAuth;
        $this->learnAPICourse = $learnAPICourse;
        $this->collaborateAPIAuth = $collaborateAPIAuth;
        $this->collaborateAPIRecordings = $collaborateAPIRecordings;

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
        $output->writeln('***** Getting learn token to use api *****');
        $learnToken = $this->learnAPIAuth->getToken();

        $output->writeln('***** Getting list of courses *****');
        $courses = $this->learnAPICourse->getIdsFromCourses($learnToken);

        $output->writeln('***** Getting collaborate token to use api *****');
        $collaborateToken = $this->collaborateAPIAuth->getToken();

        foreach ($courses as $course) {
            $output->writeln('***** Getting recordings from course *****');
            $this->collaborateAPIRecordings->getCourseRecordings($collaborateToken, $course);
        }

        return Command::SUCCESS;
    }
}
