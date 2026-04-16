<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BlackboardBundle\Document\BlackboardCourse;
use Pumukit\BlackboardBundle\Repository\BlackboardCourseRepository;

class BlackboardCourseManager
{
    private DocumentManager $documentManager;
    private BlackboardCourseRepository $repository;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
        /** @var BlackboardCourseRepository $repo */
        $repo = $documentManager->getRepository(BlackboardCourse::class);
        $this->repository = $repo;
    }

    /**
     * Upserts a course from Learn data.
     * - If new: persists with status=pending_recordings.
     * - If existing: updates name/lastSyncAt but does NOT change status,
     *   unless the course was previously in 'done' state (re-queues it to check for new recordings).
     */
    public function upsert(string $learnId, string $collaborateId, string $name): BlackboardCourse
    {
        $course = $this->repository->findByCollaborateId($collaborateId);

        if (!$course) {
            $course = BlackboardCourse::create($learnId, $collaborateId, $name);
            $this->documentManager->persist($course);
        } else {
            $course->updateInfo($name);
            // Re-queue 'done' courses so they are checked again for new recordings.
            if (BlackboardCourse::STATUS_DONE === $course->status()) {
                $course->markAsPendingRecordings();
            }
        }

        return $course;
    }

    public function flush(): void
    {
        $this->documentManager->flush();
    }

    public function findPendingRecordings(int $limit): array
    {
        return $this->repository->findPendingRecordings($limit);
    }

    public function markAsPendingImport(BlackboardCourse $course, int $recordingsCount): void
    {
        $course->markAsPendingImport($recordingsCount);
        $this->documentManager->flush();
    }

    public function markAsDone(BlackboardCourse $course): void
    {
        $course->markAsDone();
        $this->documentManager->flush();
    }

    public function markAsError(BlackboardCourse $course, string $errorMessage): void
    {
        $course->markAsError($errorMessage);
        $this->documentManager->flush();
    }
}

