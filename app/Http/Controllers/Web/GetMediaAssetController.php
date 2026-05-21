<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Media\Asset;
use App\Models\Media\Transformation;
use Illuminate\Http\Request;

/**
 * Get a media asset and redirect to the appropriate transform on CDN
 */
class GetMediaAssetController extends Controller
{
    public function __invoke(Asset $asset, Transformation $transformation, Request $request)
    {
        abort_if($asset->visibility === 'private' && ! auth()->check(), 403);
        abort_if($asset->visibility === 'private' && ! $request->hasValidSignature(), 403);

        return redirect()->away($asset->assetPath());
    }
}
