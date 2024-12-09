<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIAuth
{
    private HttpClientInterface $client;
    private CollaborateAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, CollaborateAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

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

            if($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \Exception('Unable to get collaborate token. Response status code: '.$response->getStatusCode());
            }

            $token = json_decode($response->getContent(), true);

            return $token['access_token'];

        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
