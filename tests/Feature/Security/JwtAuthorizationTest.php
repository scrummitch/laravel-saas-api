<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Client-JWT authentication layer.
 *
 * Closes off the original rejection feedback:
 *   - algorithm is pinned, header-supplied alg is rejected (alg-confusion)
 *   - `?client=` query param cannot override the JWT's `kid` claim
 *   - cart-token signature uses hash_equals and rejects malformed tokens
 */
class JwtAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function freshClient(): Client
    {
        $org = $this->createOrg();

        return $org->clients()->first() ?? Client::factory()->for($org)->create();
    }

    private function jwtAuth(string $token, array $extra = []): array
    {
        return array_merge(['Authorization' => 'Bearer '.$token], $extra);
    }

    public function test_valid_hs256_token_authenticates(): void
    {
        $client = $this->freshClient();
        $jwt = $client->generateAgentJwt('user_' . uniqid('', true));

        // Hit any /client/* endpoint that's behind ClientJwtMiddleware. Status
        // doesn't matter — anything other than 401/403 from the middleware proves
        // the token was accepted.
        $response = $this->postJson('/client/events', [], $this->jwtAuth($jwt));

        $this->assertNotContains(
            $response->status(),
            [401, 403],
            'valid HS256 token should not be rejected by the auth middleware',
        );
    }

    public function test_token_with_alg_none_is_rejected(): void
    {
        $client = $this->freshClient();

        // Hand-craft an alg=none token. Firebase JWT refuses to encode `none`,
        // so build the parts by hand.
        $header = self::b64(json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => $client->getRouteKey()]));
        $payload = self::b64(json_encode(['sub' => 'user_x', 'exp' => time() + 3600]));
        $token = "{$header}.{$payload}.";

        $this->postJson('/client/events', [], $this->jwtAuth($token))
            ->assertStatus(401);
    }

    public function test_token_with_unexpected_alg_is_rejected(): void
    {
        $client = $this->freshClient();

        // Encode with HS512 — middleware pins HS256.
        $token = JWT::encode(
            ['sub' => 'user_x', 'exp' => time() + 3600],
            $client->getSecretStr(),
            'HS512',
            $client->getRouteKey(),
        );

        $this->postJson('/client/events', [], $this->jwtAuth($token))
            ->assertStatus(401);
    }

    public function test_kid_comes_from_token_not_from_query_string(): void
    {
        $clientA = $this->freshClient();
        $clientB = Client::factory()->for($this->createOrg())->create();

        // Signed by A, but URL claims it's for B. Pre-fix this would have let
        // the request operate under B's tenant context. Now the middleware
        // ignores ?client= entirely — verification uses clientA's secret,
        // succeeds, and the request runs under clientA.
        $tokenSignedByA = $clientA->generateAgentJwt('user_x');

        $response = $this->postJson(
            '/client/events?client='.$clientB->getRouteKey(),
            [],
            $this->jwtAuth($tokenSignedByA),
        );

        $this->assertNotContains(
            $response->status(),
            [401, 403],
            'token signed by A should still authenticate as A regardless of ?client=',
        );
    }

    public function test_token_signed_by_unknown_secret_is_rejected(): void
    {
        $client = $this->freshClient();

        $token = JWT::encode(
            ['sub' => 'user_x', 'exp' => time() + 3600],
            'totally-the-wrong-secret',
            'HS256',
            $client->getRouteKey(),
        );

        $this->postJson('/client/events', [], $this->jwtAuth($token))
            ->assertStatus(401);
    }

    public function test_missing_bearer_token_is_rejected(): void
    {
        $this->postJson('/client/events')->assertStatus(401);
    }

    public function test_cart_token_with_wrong_signature_is_rejected(): void
    {
        // cartToken() isn't reached by any HTTP route in the current codebase
        // so we exercise it directly. The check used to be `!==` followed by
        // a redundant hash_equals — fixed to use hash_equals only.
        $client = $this->freshClient();

        $this->withHeader('Plandalf-Cart', 'some-cart?sig=definitely-wrong')
            ->postJson('/client/events', [], $this->jwtAuth($client->generateAgentJwt('user_x')));

        $auth = new \App\Client\ClientAuthorization($client->generateAgentJwt('user_x'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $auth->cartToken();
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
