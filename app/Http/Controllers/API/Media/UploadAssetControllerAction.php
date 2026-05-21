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

        Storage::put('public/'.$request->get('path'), $request->getContent());

        return response('', 201);
    }
}
