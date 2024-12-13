<?php

namespace Pumukit\BlackboardBundle\Services;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIAuth
{
    private HttpClientInterface $client;
    private CollaborateAPIConfiguration $configuration;
    private $assertion;
    private $grant_type;
    private $payload;
    private $header;
    private $verify_cert = true;

    public function __construct(HttpClientInterface $client, CollaborateAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
        $this->generateJWTPayload();
    }

    public function getToken(): string
    {
        try {
            $response = $this->client->request('POST', $this->configuration->apiTokenUrl(), [
                'auth_basic' => [$this->configuration->key(), $this->configuration->secret()],
                'body' => $this->payload,
                'verify_peer' => $this->verify_cert,
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get collaborate token. Response status code: '.$response->getStatusCode());
            }

            $token = json_decode($response->getContent(), true);

            return $token['access_token'];
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    private function generateJWTPayload(): void
    {
        $exp = (new \DateTimeImmutable('now'))->modify('+30 minutes')->getTimestamp();
        $claims = [
            'iss' => $this->configuration->key(),
            'sub' => $this->configuration->key(),
            'exp' => $exp,
        ];

        $this->header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $this->assertion = JWT::encode($claims, $this->configuration->secret(), 'HS256');
        $this->grant_type = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

        $this->payload = [
            'grant_type' => $this->grant_type,
            'assertion' => $this->assertion,
        ];
    }
}
