<?php

namespace App\Http\Resources\Api;

use App\Models\Media\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Asset $resource
 */
class AssetApiResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'name' => $this->name,
            'size' => $this->size,
            'content_type' => $this->content_type,
            'original_name' => $this->original_name,
            'key' => $this->key,
            'href' => $this->resource->href(),
        ];
    }
}
