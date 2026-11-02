<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIUser
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CollaborateAPIConfiguration $configuration,
    ) {}

    public function searchUser(string $accessToken, string $userId): ?array
    {
        $sessionResponse = $this->user($accessToken, $userId);

        return json_decode($sessionResponse, true);
    }

    private function user(string $accessToken, string $userId): ?string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->userUrl().'/'.$userId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get user. Response status code: '.$response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
