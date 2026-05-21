<?php

namespace App\Http\Resources\Api;

use App\Client\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientApiResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'client',
            'name' => $this->name,
            'label' => trim(implode(' ', [$this->name, $this->originList()])),
            'type' => $this->type,
            'secret' => $this->getSecretStr(),
            'platform' => $this->platform,
            'environment' => $this->environment,

            'billing_provider_id' => $this->whenLoaded('billingProvider', fn () => $this->billingProvider?->getRouteKey()),
            'exclusion_rules' => $this->exclusion_rules,

            'allowed_origins' => $this->allowed_origins,
            'pending_origins' => $this->pending_origins,

            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,

            'api_keys' => $this->tokens->map(fn (PersonalAccessToken $token) => [
                'id' => $token->hash,
                'name' => $token->name,
                'value' => $token->getApiKey(),
                'expires_at' => $token->expires_at?->timestamp,
                'last_used_at' => $token->last_used_at?->timestamp,
                'abilities' => $token->abilities,
            ]),

            'test_user_jwt' => \Firebase\JWT\JWT::encode([
                'sub' => 'test_user',
                'exp' => now()->addHour()->timestamp,
                'aud' => 'client',
            ], $this->getSecretStr(), 'HS256', $this->getRouteKey()),
        ];
    }
}
