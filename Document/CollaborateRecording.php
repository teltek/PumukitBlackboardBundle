<?php

namespace Pumukit\BlackboardBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use MongoDB\BSON\ObjectId;

/**
 * @MongoDB\Document(repositoryClass="Pumukit\BlackboardBundle\Repository\CollaborateRecordingRepository")
 */
class CollaborateRecording
{
    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Field(type="string")
     */
    private $recording;

    /**
     * @MongoDB\Field(type="string")
     */
    private $courseUUID;

    /**
     * @MongoDB\Field(type="string")
     */
    private $downloadUrl;

    /**
     * @MongoDB\Field(type="string")
     */
    private $sessionName;

    /**
     * @MongoDB\Field(type="boolean")
     */
    private $imported;

    private function __construct(\Pumukit\BlackboardBundle\ValueObject\CollaborateRecording $recording)
    {
        $this->id = new ObjectId();
        $this->recording = $recording->id();
        $this->courseUUID = $recording->courseUUID();
        $this->downloadUrl = $recording->downloadUrl();
        $this->sessionName = $recording->sessionName();
        $this->imported = false;
    }

    public static function create(\Pumukit\BlackboardBundle\ValueObject\CollaborateRecording $recording): CollaborateRecording
    {
        return new self($recording);
    }

    public function id()
    {
        return $this->id;
    }

    public function recording(): string
    {
        return $this->recording;
    }

    public function courseUUID(): string
    {
        return $this->courseUUID;
    }

    public function downloadUrl(): string
    {
        return $this->downloadUrl;
    }

    public function sessionName(): string
    {
        return $this->sessionName;
    }

    public function imported(): bool
    {
        return $this->imported;
    }
}
