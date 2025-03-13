<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LearnAPIUser
{
    private HttpClientInterface $client;
    private LearnAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, LearnAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    public function searchUserById(string $accessToken, string $userId): ?array
    {
        $user = $this->searchUser($accessToken, $userId);

        return json_decode($user, true);
    }

    private function searchUser(string $accessToken, string $userId): string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->apiUser().'/uuid:'.$userId, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get courses. Response status code: '.$response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
