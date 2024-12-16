<?php

namespace Pumukit\BlackboardBundle\ValueObject;

final class CollaborateRecording
{
    private string $id;
    private string $courseUUID;
    private string $downloadUrl;
    private string $sessionName;

    private function __construct(string $id, string $courseUUID, string $downloadUrl, string $sessionName)
    {
        $this->id = $id;
        $this->courseUUID = $courseUUID;
        $this->downloadUrl = $downloadUrl;
        $this->sessionName = $sessionName;
    }

    public static function create(string $id, string $courseUUID, string $downloadUrl, string $sessionName): CollaborateRecording
    {
        return new self($id, $courseUUID, $downloadUrl, $sessionName);
    }

    public function id(): string
    {
        return $this->id;
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
}
