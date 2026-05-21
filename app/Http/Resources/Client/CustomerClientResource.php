<?php

namespace App\Http\Resources\Client;

use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerClientResource extends JsonResource
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
            'object' => 'customer',
            'internal_id' => $this->when(app()->hasDebugModeEnabled(), $this->getKey()),
            'email' => $this->email,
            'name' => $this->name,
            'primary_payment_method' => $this->primaryPaymentMethod?->reference_id,
            'payment_methods' => [
                'object' => 'list',
                'data' => $this
                    ->paymentMethods()
                    ->map(fn (Twin $twin) => $twin->object()),
            ],
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }
}
