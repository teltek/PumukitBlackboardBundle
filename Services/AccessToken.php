<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

final class AccessToken
{
    private const MARGIN_SECONDS = 60;
    private const DEFAULT_LIFETIME_SECONDS = 600;

    private function __construct(
        private readonly string $value,
        private readonly int $expiresAt,
    ) {}

    public static function fromResponse(array $data, int $now): self
    {
        if (!isset($data['access_token']) || !is_string($data['access_token'])) {
            throw new \RuntimeException('The token response has no access_token');
        }

        $lifetime = (int) ($data['expires_in'] ?? self::DEFAULT_LIFETIME_SECONDS);

        return new self($data['access_token'], $now + max(0, $lifetime - self::MARGIN_SECONDS));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isValidAt(int $now): bool
    {
        return $now < $this->expiresAt;
    }
}
