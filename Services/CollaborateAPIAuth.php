<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIAuth
{
    private const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    private ?AccessToken $token = null;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CollaborateAPIConfiguration $configuration,
    ) {}

    public function getToken(): string
    {
        $now = time();
        if ($this->token?->isValidAt($now)) {
            return $this->token->value();
        }

        $response = $this->client->request('POST', $this->configuration->apiTokenUrl(), [
            'auth_basic' => [$this->configuration->key(), $this->configuration->secret()],
            'body' => [
                'grant_type' => self::GRANT_TYPE,
                'assertion' => $this->assertion($now),
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new \RuntimeException('Unable to get collaborate token. Response status code: '.$response->getStatusCode());
        }

        $this->token = AccessToken::fromResponse($response->toArray(), $now);

        return $this->token->value();
    }

    private function assertion(int $now): string
    {
        return JWT::encode([
            'iss' => $this->configuration->key(),
            'sub' => $this->configuration->key(),
            'exp' => $now + 30 * 60,
        ], $this->configuration->secret(), 'HS256');
    }
}
