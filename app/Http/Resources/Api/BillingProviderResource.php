<?php

namespace App\Http\Resources\Api;

use App\Models\Billing\BillingProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class BillingProviderResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'provider_id' => $this->lookup_key,
            'internal_id' => $this->when(! app()->isProduction(), fn () => $this->id),
            'name' => $this->name,
            'type' => $this->type,
            'current_state' => $this->current_state,
            'environment' => $this->environment,
            'operations' => OperationApiResource::collection($this->operations),
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'external_url' => $this->externalUrl(),
            'models' => $this->models(),
            'integration' => BillingProvider::integrationDescriptor($this->type),
        ];
    }

    private function models()
    {
        if ($this->type === 'stripe') {
            return DB::table('twins')
                ->where('connector_id', $this->id)
                ->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->get();
        }

        return null;
    }
}
