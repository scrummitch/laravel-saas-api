<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThemeApiResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'theme',

            'border_radius' => $this->border_radius,
            'page_bg_color' => $this->page_bg_color,
            'button_bg' => $this->button_bg,
            'button_text_color' => $this->button_text_color,
            'link_color' => $this->link_color,
            'base_font_size' => $this->base_font_size,
            'paragraph_line_height' => $this->paragraph_line_height,
            'font_family' => $this->font_family,
            'base_text_color' => $this->base_text_color,
            'vertical_spacing' => $this->vertical_spacing,
            'input_size' => $this->input_size,
            'input_variant' => $this->input_variant,
            'input_color' => $this->input_color,
            'accent_color' => $this->accent_color,

            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            //            'heading_line_height' => $this->heading_line_height,
        ];
    }
}
