<?php

namespace Pumukit\BlackboardBundle\Services;

class LearnAPIConfiguration
{
    private const API_BASE_PATH = '/learn/api/public';
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
        return $this->host().self::API_BASE_PATH.'/v1/oauth2/token';
    }

    public function apiCourseListUrl(): string
    {
        return $this->host().self::API_BASE_PATH.'/v3/courses';
    }
}
