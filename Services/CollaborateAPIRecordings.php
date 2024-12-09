<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPIRecordings
{
    private HttpClientInterface $client;
    private CollaborateAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, CollaborateAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    private function recordings(string $accessToken, string $courseId): string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->apiRecordingUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'externalCourseId' => $courseId,
                ],
            ]);

            if($response->getStatusCode() !== Response::HTTP_OK) {
                throw new \Exception('Unable to get courses. Response status code: '.$response->getStatusCode());
            }

            return $response->getContent();

        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    public function getCourseRecordings(string $accessToken, string $courseId): array
    {
        $recordingsResponse = $this->recordings($accessToken, $courseId);

        return json_decode($recordingsResponse, true);
    }
}
