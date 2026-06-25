<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPISessionSearch
{
    private HttpClientInterface $client;
    private CollaborateAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, CollaborateAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    public function searchSessions(string $accessToken): array
    {
        $offset = 0;
        $limit = 10000;

        $allResults = [];

        do {
            $sessionResponse = $this->session(
                $accessToken,
                $offset,
                $limit
            );

            $data = json_decode($sessionResponse, true);

            $results = $data['results'] ?? [];

            $allResults = array_merge(
                $allResults,
                $results
            );

            $offset += $limit;
        } while (count($results) === $limit);

        return [
            'results' => $allResults,
        ];
    }

    public function getEnrollmentsBySessionId(string $accessToken, string $sessionId): ?array
    {
        $sessionResponse = $this->sessionById($accessToken, $sessionId);

        return json_decode($sessionResponse, true);
    }

    private function session(string $accessToken, int $offset = 0, int $limit = 10000): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->configuration->sessionDataUrl(),
                [
                    'query' => [
                        'offset' => $offset,
                        'limit' => $limit,
                    ],
                    'headers' => [
                        'Authorization' => 'Bearer '.$accessToken,
                        'Accept' => 'application/json',
                    ],
                ]
            );

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception(
                    'Unable to get session. Response status code: '.$response->getStatusCode()
                );
            }

            return $response->getContent();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    private function sessionById(string $accessToken, string $sesionId): ?string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->sessionDataUrl().'/'.$sesionId.'/enrollments', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get session. Response status code: '.$response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
