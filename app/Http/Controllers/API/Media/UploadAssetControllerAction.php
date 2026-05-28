<?php

namespace App\Http\Controllers\API\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Upload an asset to the media filesystem.
 */
class UploadAssetControllerAction extends Controller
{
    public function __invoke(Request $request)
    {
        abort_if(config('filesystems.media') !== 'local', 400, 'The media filesystem is not local');
        abort_if(! $request->hasValidSignature(), 400);

        $path = (string) $request->get('path');

        // Defence in depth: the signed URL binds the path, but a bug in URL
        // generation could otherwise let a write escape the assets/ subtree.
        abort_unless(
            $path !== ''
                && ! str_contains($path, '..')
                && ! str_contains($path, '\\')
                && ! str_starts_with($path, '/')
                && str_starts_with($path, 'assets/'),
            400,
            'Invalid upload path.',
        );

        Storage::put('public/'.$path, $request->getContent());

        return response('', 201);
    }
}
