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

    public function searchBySessionName(string $accessToken, string $sessionName): ?array
    {
        $sessionResponse = $this->sessionByName($accessToken, $sessionName);

        return json_decode($sessionResponse, true);
    }

    public function getEnrollmentsBySessionId(string $accessToken, string $sessionId): ?array
    {
        $sessionResponse = $this->sessionById($accessToken, $sessionId);

        return json_decode($sessionResponse, true);
    }

    private function sessionByName(string $accessToken, string $sessionName): ?string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->sessionDataUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
//                'query' => [
//                    'name' => $sessionName,
//                ],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get session. Response status code: '.$response->getStatusCode());
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
                ]
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
