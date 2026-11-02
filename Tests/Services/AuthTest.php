<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Tests\Services;

use PHPUnit\Framework\TestCase;
use Pumukit\BlackboardBundle\Services\CollaborateAPIAuth;
use Pumukit\BlackboardBundle\Services\CollaborateAPIConfiguration;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Pumukit\BlackboardBundle\Services\LearnAPIConfiguration;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @coversNothing
 */
class AuthTest extends TestCase
{
    public function testTheCollaborateTokenIsReusedWhileItIsValid(): void
    {
        $requests = 0;
        $client = $this->client(3600, $requests);
        $auth = new CollaborateAPIAuth($client, new CollaborateAPIConfiguration('https://collab.example.org', 'key', 'a-secret-long-enough-for-hs256-signatures'));

        $this->assertSame('token-1', $auth->getToken());
        $this->assertSame('token-1', $auth->getToken());
        $this->assertSame(1, $requests);
    }

    public function testTheCollaborateTokenIsRequestedAgainWhenItExpires(): void
    {
        $requests = 0;
        $client = $this->client(30, $requests);
        $auth = new CollaborateAPIAuth($client, new CollaborateAPIConfiguration('https://collab.example.org', 'key', 'a-secret-long-enough-for-hs256-signatures'));

        $this->assertSame('token-1', $auth->getToken());
        $this->assertSame('token-2', $auth->getToken());
    }

    public function testTheLearnTokenIsReusedWhileItIsValid(): void
    {
        $requests = 0;
        $auth = new LearnAPIAuth($this->client(3600, $requests), new LearnAPIConfiguration('https://learn.example.org', 'key', 'secret'));

        $auth->getToken();
        $auth->getToken();
        $this->assertSame(1, $requests);
    }

    public function testAFailedTokenRequestIsReported(): void
    {
        $auth = new LearnAPIAuth(new MockHttpClient(new MockResponse('', ['http_code' => 401])), new LearnAPIConfiguration('https://learn.example.org', 'key', 'secret'));

        $this->expectExceptionMessage('401');

        $auth->getToken();
    }

    private function client(int $expiresIn, int &$requests): MockHttpClient
    {
        return new MockHttpClient(function () use ($expiresIn, &$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode(['access_token' => 'token-'.$requests, 'expires_in' => $expiresIn]));
        });
    }
}
