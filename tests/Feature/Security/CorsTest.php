<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Per-client CORS allowlist on /client/*.
 *
 * The fallback used to set `allowed_origins => ['*']`. It now reads the
 * `?client=<ulid>` query string the SDK sends on every request (preflight
 * included), looks up that one client's `allowed_origins` (cached), and
 * echoes the origin back only on a match.
 */
class CorsTest extends TestCase
{
    use DatabaseTransactions;

    private function clientWithOrigins(array $origins): Client
    {
        $org = $this->createOrg();
        $client = $org->clients()->first() ?? Client::factory()->for($org)->create();
        $client->allowed_origins = $origins;
        $client->save();

        return $client;
    }

    public function test_preflight_with_allowed_origin_echoes_origin_back(): void
    {
        $client = $this->clientWithOrigins(['https://app.example.com']);

        $response = $this->call(
            'OPTIONS',
            '/client/events?client='.$client->getRouteKey(),
            [], [], [],
            [
                'HTTP_ORIGIN' => 'https://app.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $this->assertSame('https://app.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_preflight_with_unknown_origin_does_not_echo_origin(): void
    {
        $client = $this->clientWithOrigins(['https://app.example.com']);

        $response = $this->call(
            'OPTIONS',
            '/client/events?client='.$client->getRouteKey(),
            [], [], [],
            [
                'HTTP_ORIGIN' => 'https://evil.example.org',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $this->assertNotSame(
            'https://evil.example.org',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_preflight_without_client_param_does_not_echo_origin(): void
    {
        $this->clientWithOrigins(['https://app.example.com']);

        $response = $this->call(
            'OPTIONS',
            '/client/events',
            [], [], [],
            [
                'HTTP_ORIGIN' => 'https://app.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('https://app.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_global_trusted_origin_is_accepted_without_client_param(): void
    {
        config(['cors.allowed_origins' => ['https://trusted.internal']]);

        $response = $this->call(
            'OPTIONS',
            '/client/events',
            [], [], [],
            [
                'HTTP_ORIGIN' => 'https://trusted.internal',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        );

        $this->assertSame('https://trusted.internal', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
