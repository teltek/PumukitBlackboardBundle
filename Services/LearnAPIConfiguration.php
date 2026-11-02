<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

class LearnAPIConfiguration
{
    private const API_BASE_PATH = '/learn/api/public';

    public function __construct(
        private readonly string $host,
        private readonly string $key,
        private readonly string $secret,
    ) {}

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

    public function apiUser(): string
    {
        return $this->host().self::API_BASE_PATH.'/v1/users';
    }
}
