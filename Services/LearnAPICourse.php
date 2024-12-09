<?php

namespace Pumukit\BlackboardBundle\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LearnAPICourse
{
    private HttpClientInterface $client;
    private LearnAPIConfiguration $configuration;

    public function __construct(HttpClientInterface $client, LearnAPIConfiguration $configuration)
    {
        $this->client = $client;
        $this->configuration = $configuration;
    }

    private function listCourses(string $accessToken): string
    {
        try {
            $response = $this->client->request('GET', $this->configuration->apiCourseListUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
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

    public function getIdsFromCourses(string $accessToken): array
    {
        $coursesResponse = $this->listCourses($accessToken);
        $courses = json_decode($coursesResponse, true);

        $courseIds = [];
        foreach ($courses['results'] as $course) {
            $courseIds[] = $course['courseId'];
        }

        return $courseIds;
    }
}
