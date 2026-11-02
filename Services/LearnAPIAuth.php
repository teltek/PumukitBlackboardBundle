<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LearnAPIAuth
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LearnAPIConfiguration $configuration,
    ) {}

    public function getToken(): string
    {
        try {
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
                throw new \Exception('Unable to get learn token. Response status code: '.$response->getStatusCode());
            }

            $token = json_decode($response->getContent(), true);

            return $token['access_token'];
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
