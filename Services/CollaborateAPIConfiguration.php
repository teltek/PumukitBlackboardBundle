<?php

namespace Pumukit\BlackboardBundle\Services;

class CollaborateAPIConfiguration
{
    private string $host;
    private string $key;
    private string $secret;
    private CONST API_BASE_PATH = '/collab/api/csa';

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
        return $this->host() . self::API_BASE_PATH;
    }

    public function apiTokenUrl(): string
    {
        return $this->host() . self::API_BASE_PATH . '/webtoken';
    }

    public function apiRecordingUrl(): string
    {
        return $this->host() . self::API_BASE_PATH . '/recordings';
    }
}
