<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Tests\Services;

use Pumukit\BlackboardBundle\Document\BlackboardCourse;
use Pumukit\BlackboardBundle\Services\BlackboardCourseManager;
use Pumukit\CoreBundle\Tests\PumukitTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class BlackboardCourseManagerTest extends PumukitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->dm->getDocumentCollection(BlackboardCourse::class)->deleteMany([]);
    }

    public function testACourseInErrorIsQueuedAgainOnTheNextSync(): void
    {
        $manager = self::getContainer()->get(BlackboardCourseManager::class);
        $course = $manager->upsert('learn-1', 'collab-1', 'Course');
        $manager->flush();
        $manager->markAsError($course, 'Timeout');

        $course = $manager->upsert('learn-1', 'collab-1', 'Course renamed');
        $manager->flush();

        $this->assertSame(BlackboardCourse::STATUS_PENDING_RECORDINGS, $course->status());
        $this->assertNull($course->errorMessage());
        $this->assertSame('Course renamed', $course->name());
        $this->assertCount(1, $manager->findPendingRecordings(10));
    }
}
