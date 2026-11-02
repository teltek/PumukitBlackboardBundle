<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Tests\Command;

use Pumukit\BlackboardBundle\Command\ImportRecordingsCommand;
use Pumukit\BlackboardBundle\Document\CollaborateRecording;
use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPIConfiguration;
use Pumukit\BlackboardBundle\Services\CollaborateAPIRecording;
use Pumukit\BlackboardBundle\ValueObject\CollaborateRecording as CollaborateRecordingValue;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @coversNothing
 */
class ImportRecordingsCommandTest extends PumukitTestCase
{
    private const HOST = 'https://collaborate.example.org';

    private string $tmpPath;
    private string $downloadExtension = 'mp4';

    public function setUp(): void
    {
        parent::setUp();
        $this->dm->getDocumentCollection(CollaborateRecording::class)->deleteMany([]);
        $this->tmpPath = sys_get_temp_dir().'/blackboard-test-'.uniqid();
        mkdir($this->tmpPath);

        $role = new Role();
        $role->setCod('owner');
        $this->dm->persist($role);
        $this->dm->flush();
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->tmpPath.'/*') ?: []);
        rmdir($this->tmpPath);
        parent::tearDown();
    }

    public function testTheRecordingIsImportedWithAllItsOwnersOnce(): void
    {
        $this->user('teacher@example.org');
        $this->user('assistant@example.org');
        $this->recording('rec-1', ['teacher@example.org' => 'teacher', 'assistant@example.org' => 'assistant']);

        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $this->dm->clear();
        $multimediaObjects = $this->dm->getRepository(MultimediaObject::class)->findBy(['properties.blackboard_recording' => 'rec-1']);
        $this->assertCount(1, $multimediaObjects);
        $this->assertCount(2, $multimediaObjects[0]->getPeopleByRoleCod('owner', true));
        $this->assertCount(2, $multimediaObjects[0]->getProperty('owners'));
        $this->assertTrue($this->dm->getRepository(CollaborateRecording::class)->findOneBy(['recording' => 'rec-1'])->imported());

        $this->assertSame(Command::SUCCESS, $this->tester()->execute([]));
        $this->assertCount(1, $this->dm->getRepository(MultimediaObject::class)->findBy(['properties.blackboard_recording' => 'rec-1']));
    }

    public function testAFailedJobLeavesNoMultimediaObjectNorFile(): void
    {
        $this->downloadExtension = 'exe';
        $this->user('teacher@example.org');
        $this->recording('rec-2', ['teacher@example.org' => 'teacher']);

        $this->assertSame(Command::FAILURE, $this->tester()->execute([]));

        $this->dm->clear();
        $this->assertSame(0, $this->dm->getRepository(MultimediaObject::class)->count(['properties.blackboard_recording' => 'rec-2']));
        $this->assertFalse($this->dm->getRepository(CollaborateRecording::class)->findOneBy(['recording' => 'rec-2'])->imported());
        $this->assertSame([], glob($this->tmpPath.'/*'));
    }

    public function testAnOwnerWithoutPersonIsNotEnoughToImport(): void
    {
        $user = new User();
        $user->setUsername('noperson');
        $user->setEmail('noperson@example.org');
        $user->setPassword('unused');
        $this->dm->persist($user);
        $this->recording('rec-3', ['noperson@example.org' => 'noperson']);

        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $this->assertStringContainsString('Skipping', $tester->getDisplay());
        $this->assertSame(0, $this->dm->getRepository(MultimediaObject::class)->count(['properties.blackboard_recording' => 'rec-3']));
    }

    public function testARecordingAlreadyInPumukitIsOnlyMarkedAsImported(): void
    {
        $factoryService = self::getContainer()->get(FactoryService::class);
        $multimediaObject = $factoryService->createMultimediaObject($factoryService->createSeries());
        $multimediaObject->setProperty('blackboard_recording', 'rec-4');
        $this->user('teacher@example.org');
        $this->recording('rec-4', ['teacher@example.org' => 'teacher']);

        $this->assertSame(Command::SUCCESS, $this->tester()->execute([]));

        $this->dm->clear();
        $this->assertSame(1, $this->dm->getRepository(MultimediaObject::class)->count(['properties.blackboard_recording' => 'rec-4']));
        $this->assertTrue($this->dm->getRepository(CollaborateRecording::class)->findOneBy(['recording' => 'rec-4'])->imported());
    }

    private function tester(): CommandTester
    {
        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_ends_with($url, '/token')) {
                return new MockResponse(json_encode(['access_token' => 'token', 'expires_in' => 3600]));
            }
            if (str_contains($url, '/recordings/') && str_ends_with($url, '/data')) {
                return new MockResponse(json_encode(['mediaDownloadUrl' => 'https://media.example.org/video.'.$this->downloadExtension]));
            }

            return new MockResponse('not a real video');
        });
        $configuration = new CollaborateAPIConfiguration(self::HOST, 'key', 'a-secret-long-enough-for-hs256-signatures');
        $container = self::getContainer();

        return new CommandTester(new ImportRecordingsCommand(
            $this->dm,
            $client,
            $container->get(FactoryService::class),
            $container->get(JobCreator::class),
            $container->get(i18nService::class),
            $container->get(PersonService::class),
            new CollaborateAPIAuth($client, $configuration),
            new CollaborateAPIRecording($client, $configuration),
            $this->tmpPath,
            true,
        ));
    }

    private function user(string $email): User
    {
        $person = new Person();
        $person->setEmail($email);
        $this->dm->persist($person);

        $user = new User();
        $user->setUsername(strstr($email, '@', true));
        $user->setEmail($email);
        $user->setPassword('unused');
        $user->setPerson($person);
        $person->setUser($user);
        $this->dm->persist($user);
        $this->dm->flush();

        return $user;
    }

    private function recording(string $id, array $owners): void
    {
        $value = CollaborateRecordingValue::create($id, 'course-uuid', 'Course', 'https://old.example.org/video.mp4', 'Session', 'Recording '.$id, '2026-10-01T10:00:00Z');
        $value->addOwners($owners);
        $this->dm->persist(CollaborateRecording::create($value));
        $this->dm->flush();
    }
}
