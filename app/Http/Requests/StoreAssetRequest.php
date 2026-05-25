<?php

namespace App\Http\Requests;

use App\Models\Media\Asset;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Asset::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * Extensions an upload is allowed to carry. Deliberately conservative:
     * adding more requires a security review (especially anything the
     * webserver might interpret — .php, .phtml, .html, .svg, .htaccess).
     */
    public const ALLOWED_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif',
        'pdf', 'csv', 'txt', 'json',
        'mp4', 'webm', 'mov',
        'woff', 'woff2', 'ttf', 'otf',
    ];

    public function rules(): array
    {
        return [
            'original_name' => ['required', 'string', 'max:255'],
            'content_type' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0', 'max:524288000'], // 500 MB
            'visibility' => ['nullable', 'in:public-read,private'],
            'cache_control' => ['nullable', 'string', 'max:255'],
            'expires' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function passedValidation(): void
    {
        $ext = strtolower(pathinfo($this->input('original_name'), PATHINFO_EXTENSION));

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            abort(422, 'File extension ".'.$ext.'" is not allowed.');
        }
    }
}
