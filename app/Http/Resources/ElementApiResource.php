<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElementApiResource extends JsonResource
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
            'display_name' => $this->display_name,
            'type' => $this->type->name,
            'mode' => $this->mode === 0 ? 'tracked' : 'managed',
            'view' => $this->view,
            'template' => $this->template,
            'conditions' => $this->conditions,
        ];
    }
}
