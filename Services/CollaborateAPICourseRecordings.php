<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CollaborateAPICourseRecordings
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CollaborateAPIConfiguration $configuration,
    ) {}

    public function getCourseRecordings(string $accessToken, string $courseId): array
    {
        $recordingsResponse = $this->recordings($accessToken, $courseId);

        return json_decode($recordingsResponse, true);
    }

    private function recordings(string $accessToken, string $courseId): string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->apiRecordingUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'contextExtId' => $courseId,
                ],
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new \Exception('Unable to get course recordings. Response status code: '.$response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}
