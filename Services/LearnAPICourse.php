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

    public function getCourses(string $accessToken): array
    {
        $courses = [];
        $url = $this->configuration->apiCourseListUrl().'?sort=modified&order=desc';

        do {
            $coursesResponse = $this->listCourses($accessToken, $url);
            $data = json_decode($coursesResponse, true);

            foreach ($data['results'] as $course) {
                $courses[] = [
                    'learnId' => $course['id'],
                    'collaborateId' => $course['uuid'],
                    'name' => $course['name'],
                ];
            }

            $url = isset($data['paging']['nextPage'])
                ? $this->configuration->host().$data['paging']['nextPage']
                : null;
        } while (null !== $url);

        return $courses;
    }

    public function getIdsFromCourses(string $accessToken): array
    {
        $courseIds = [];
        foreach ($this->getCourses($accessToken) as $course) {
            $courseIds[$course['collaborateId']] = $course['name'];
        }

        return $courseIds;
    }

    private function listCourses(string $accessToken, string $url): string
    {
        try {
            $response = $this->client->request('GET', $url, [
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
