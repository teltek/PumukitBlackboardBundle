<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LearnAPIAuth
{
    private ?AccessToken $token = null;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LearnAPIConfiguration $configuration,
    ) {}

    public function getToken(): string
    {
        $now = time();
        if ($this->token?->isValidAt($now)) {
            return $this->token->value();
        }

        $response = $this->client->request('POST', $this->configuration->apiTokenUrl(), [
            'auth_basic' => [$this->configuration->key(), $this->configuration->secret()],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new \RuntimeException('Unable to get learn token. Response status code: '.$response->getStatusCode());
        }

        $this->token = AccessToken::fromResponse($response->toArray(), $now);

        return $this->token->value();
    }
}
