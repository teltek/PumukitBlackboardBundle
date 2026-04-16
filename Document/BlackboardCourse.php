<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use MongoDB\BSON\ObjectId;

/**
 * @MongoDB\Document(repositoryClass="Pumukit\BlackboardBundle\Repository\BlackboardCourseRepository")
 *
 * @MongoDB\Index(keys={"collaborateId"="asc"}, options={"unique"=true})
 */
class BlackboardCourse
{
    public const STATUS_PENDING_RECORDINGS = 0;
    public const STATUS_PENDING_IMPORT = 1;
    public const STATUS_DONE = 2;
    public const STATUS_ERROR = 3;

    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Field(type="string")
     */
    private string $learnId;

    /**
     * @MongoDB\Field(type="string")
     */
    private string $collaborateId;

    /**
     * @MongoDB\Field(type="string")
     */
    private string $name;

    /**
     * @MongoDB\Field(type="int")
     */
    private int $status;

    /**
     * @MongoDB\Field(type="int")
     */
    private int $recordingsCount = 0;

    /**
     * @MongoDB\Field(type="date")
     */
    private \DateTime $lastSyncAt;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    private ?string $errorMessage = null;

    private function __construct(string $learnId, string $collaborateId, string $name)
    {
        $this->id = new ObjectId();
        $this->learnId = $learnId;
        $this->collaborateId = $collaborateId;
        $this->name = $name;
        $this->status = self::STATUS_PENDING_RECORDINGS;
        $this->lastSyncAt = new \DateTime();
    }

    public static function create(string $learnId, string $collaborateId, string $name): self
    {
        return new self($learnId, $collaborateId, $name);
    }

    public function id(): ObjectId
    {
        return $this->id;
    }

    public function learnId(): string
    {
        return $this->learnId;
    }

    public function collaborateId(): string
    {
        return $this->collaborateId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function recordingsCount(): int
    {
        return $this->recordingsCount;
    }

    public function lastSyncAt(): \DateTime
    {
        return $this->lastSyncAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function updateInfo(string $name): void
    {
        $this->name = $name;
        $this->lastSyncAt = new \DateTime();
    }

    public function markAsPendingRecordings(): void
    {
        $this->status = self::STATUS_PENDING_RECORDINGS;
        $this->errorMessage = null;
        $this->lastSyncAt = new \DateTime();
    }

    public function markAsPendingImport(int $recordingsCount): void
    {
        $this->status = self::STATUS_PENDING_IMPORT;
        $this->recordingsCount = $recordingsCount;
        $this->errorMessage = null;
        $this->lastSyncAt = new \DateTime();
    }

    public function markAsDone(): void
    {
        $this->status = self::STATUS_DONE;
        $this->recordingsCount = 0;
        $this->errorMessage = null;
        $this->lastSyncAt = new \DateTime();
    }

    public function markAsError(string $errorMessage): void
    {
        $this->status = self::STATUS_ERROR;
        $this->errorMessage = $errorMessage;
        $this->lastSyncAt = new \DateTime();
    }
}
