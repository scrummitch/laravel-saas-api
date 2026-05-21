<?php

namespace App\Models\Convert;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $border_radius (none, sm, md, lg, xl, full)
 * @property string $page_bg_color
 * @property string $button_bg ? support gradients? vertical only?
 * @property string $button_text_color
 * @property string $link_color
 * @property string $base_font_size
 * @property string $paragraph_line_height (1.5, 2.5)
 * @property string $heading_line_height
 * @property string $font_family
 * @property string $base_text_color
 * @property string $vertical_spacing (sm, md, lg)
 * @property string $input_size (1, 2, 3)
 * @property string $input_variant classic, surface, soft, stripe
 * @property string $input_color # hex ??
 * @property string $accent_color # hex ??
 */
class Theme extends Model
{
    use HasFactory,
        HasNiceUlids;

    const COLOR_VALIDATION = 'regex:/^(#(?:[0-9a-f]{2}){2,4}|#[0-9a-f]{3}|(?:rgba?|hsla?)\((?:\d+%?(?:deg|rad|grad|turn)?(?:,|\s)+){2,3}[\s\/]*[\d\.]+%?\))$/i';

    protected $table = 'convert_themes';

    public static function newWithDefaults(): self
    {
        self::unguard();
        $theme = new static([
            'border_radius' => 'm',
            'page_bg_color' => '#ffffff',
            'button_bg' => '#007bff',
            'button_text_color' => '#ffffff',
            'link_color' => '#007bff',
            'base_font_size' => '16',
            'base_text_color' => '#212529',
            'paragraph_line_height' => '1.5',
            'font_family' => 'Arial, sans-serif',
            'vertical_spacing' => 'md',
            'input_size' => '2',
            'input_variant' => 'stripe',
            'input_color' => '#f8f9fa',
            'accent_color' => '#007bff',
        ]);
        self::reguard();

        return $theme;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
