<?php

namespace App\Http\Resources;

use App\Http\Resources\Api\OrganizationApiResource;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getRouteKey(),
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'timezone' => $this->timezone,
            'email_verified_at' => $this->email_verified_at,
            'plandalf_jwt' => JWT::encode([
                'sub' => $this->getRouteKey(),
            ], $this->getSecretStr(), 'HS256', $this->getClientId()),
            'current_organization' => OrganizationApiResource::make($this->currentOrganization),
            'organizations' => OrganizationApiResource::collection($this->organizations),
        ];
    }

    protected function getSecretStr()
    {
        return config('services.plandalf.secret');
    }

    public static $wrap = null;

    private function getClientId()
    {
        return config('services.plandalf.client_id');
    }
}
