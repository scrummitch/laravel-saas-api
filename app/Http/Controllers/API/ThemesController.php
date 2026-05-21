<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ThemeApiResource;
use App\Models\Convert\Theme;
use App\Models\Management\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemesController extends Controller
{
    public function index(Request $request)
    {
        /* @var Organization $org */
        $org = $request->user()->currentOrganization;

        $query = $org
            ->themes();

        return ThemeApiResource::collection($query->paginate());
    }

    public function update(Theme $theme, Request $request)
    {
        $validated = $request->validate([
            'border_radius' => [
                Rule::in(['none', 'sm', 'md', 'lg', 'xl', 'full']),
            ],
            'page_bg_color' => [
                Theme::COLOR_VALIDATION,
            ],
            'button_bg' => [
                'required',
            ],
            'button_text_color' => [
                Theme::COLOR_VALIDATION,
            ],
            'link_color' => [
                Theme::COLOR_VALIDATION,
            ],
            'base_font_size' => [
                'integer',
                'min:10',
                'max:40',
            ],
            'base_text_color' => [
                Theme::COLOR_VALIDATION,
            ],
            'paragraph_line_height' => [
                'numeric',
                'min:0.1',
                'max:2.5',
            ],
            'font_family' => [

            ],
            'vertical_spacing' => [
                Rule::in(['sm', 'md', 'lg']),
            ],
            'input_size' => [
                Rule::in(['1', '2', '3']),
            ],
            'input_variant' => [
                Rule::in(['classic', 'surface', 'soft', 'stripe']),
            ],
            'input_color' => [
                Theme::COLOR_VALIDATION,
            ],
            'accent_color' => [
                Theme::COLOR_VALIDATION,
            ],
        ]);

        Theme::unguard();
        $theme->update($validated);
        Theme::reguard();

        return new ThemeApiResource($theme);
    }
}
