<?php

namespace Pumukit\BlackboardBundle\Services;

class CollaborateAPIConfiguration
{
    private const API_BASE_PATH = '';
    private string $host;
    private string $key;
    private string $secret;

    public function __construct(string $host, string $key, string $secret)
    {
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function apiUrl(): string
    {
        return $this->host().self::API_BASE_PATH;
    }

    public function apiTokenUrl(): string
    {
        return $this->host().self::API_BASE_PATH.'/token';
    }

    public function apiRecordingUrl(): string
    {
        return $this->host().self::API_BASE_PATH.'/recordings';
    }

    public function recordingDataUrl(string $recordingId): string
    {
        return $this->host().self::API_BASE_PATH.'/recordings/'.$recordingId.'/data';
    }

    public function recordingInfoUrl(string $recordingId): string
    {
        return $this->host().self::API_BASE_PATH.'/recordings/'.$recordingId;
    }

    public function sessionDataUrl(): string
    {
        return $this->host().self::API_BASE_PATH.'/sessions';
    }

    public function userUrl(): string
    {
        return $this->host().self::API_BASE_PATH.'/users';
    }
}
