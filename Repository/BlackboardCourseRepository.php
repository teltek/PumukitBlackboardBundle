<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Repository;

use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Pumukit\BlackboardBundle\Document\BlackboardCourse;

class BlackboardCourseRepository extends DocumentRepository
{
    public function findByCollaborateId(string $collaborateId): ?BlackboardCourse
    {
        return $this->findOneBy(['collaborateId' => $collaborateId]);
    }

    public function findPendingRecordings(int $limit): array
    {
        return $this->createQueryBuilder()
            ->field('status')->equals(BlackboardCourse::STATUS_PENDING_RECORDINGS)
            ->limit($limit)
            ->getQuery()
            ->execute()
            ->toArray()
        ;
    }
}

