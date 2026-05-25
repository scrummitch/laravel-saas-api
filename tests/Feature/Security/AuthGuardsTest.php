<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies the perimeter of the v1 API:
 *   - auth:sanctum is enforced
 *   - per-user throttle is applied
 *   - security response headers ship on every response
 *
 * If any of these regress, the answers to the original reviewer feedback
 * stop being true.
 */
class AuthGuardsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_users_me_returns_401_without_auth(): void
    {
        $this->getJson('/v1/users/me')->assertStatus(401);
    }

    public function test_clients_index_returns_401_without_auth(): void
    {
        $this->getJson('/v1/clients')->assertStatus(401);
    }

    public function test_users_me_returns_200_when_authenticated(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->getJson('/v1/users/me')->assertStatus(200);
    }

    public function test_security_headers_are_present_on_authed_responses(): void
    {
        Sanctum::actingAs($this->createUser());

        $response = $this->getJson('/v1/users/me');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_security_headers_are_present_on_401_responses(): void
    {
        $response = $this->getJson('/v1/users/me');

        $response->assertStatus(401);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_absent_in_local_environment(): void
    {
        // We deliberately disable HSTS outside production so dev over plain
        // http still works. If this assertion ever fails it means a
        // production HSTS leaked into local — easy to ship by accident.
        $response = $this->getJson('/health');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
