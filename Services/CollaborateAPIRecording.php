<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIRecording
{
    private HttpClientInterface $client;
    private CollaborateAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, CollaborateAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    public function getRecordingData(string $accessToken, string $recording): ?array
    {
        $path = $this->configuration->recordingDataUrl($recording);
        $recordingsResponse = $this->recordings($accessToken, $path);

        return json_decode($recordingsResponse, true);
    }

    public function getRecordingInfo(string $accessToken, string $recording): ?array
    {
        $path = $this->configuration->recordingInfoUrl($recording);
        $recordingsResponse = $this->recordings($accessToken, $path);

        return json_decode($recordingsResponse, true);
    }

    private function recordings(string $accessToken, string $path): ?string
    {
        $response = $this->client->request('GET', $path, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'verify_peer' => true,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
        }

        return $response->getContent();
    }
}
