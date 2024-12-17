<?php

namespace Pumukit\BlackboardBundle\ValueObject;

final class CollaborateRecording
{
    private string $id;
    private string $courseUUID;
    private string $downloadUrl;
    private string $sessionName;
    private string $title;
    private string $created;

    private function __construct(string $id, string $courseUUID, string $downloadUrl, string $sessionName, string $title, string $created)
    {
        $this->id = $id;
        $this->courseUUID = $courseUUID;
        $this->downloadUrl = $downloadUrl;
        $this->sessionName = $sessionName;
        $this->title = $title;
        $this->created = $created;
    }

    public static function create(string $id, string $courseUUID, string $downloadUrl, string $sessionName, string $title, string $created): CollaborateRecording
    {
        return new self($id, $courseUUID, $downloadUrl, $sessionName, $title, $created);
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

    public function title(): string
    {
        return $this->title;
    }

    public function created(): string
    {
        return $this->created;
    }
}
