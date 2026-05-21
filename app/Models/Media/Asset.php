<?php

namespace App\Models\Media;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Http\Controllers\API\Media\StoreAssetControllerAction;
use App\Models\Management\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property string $ulid
 * @property int $organization_id
 * @property int $user_id
 * @property string $name
 * @property mixed|string $current_state
 * @property string $type
 * @property string $original_name
 * @property string $content_type // mime type
 * @property int $size // in bytes
 * @property string $key // s3 key
 * @property string $visibility // public-read/private
 * @property string $disk
 * @property string $bucket
 */
class Asset extends Model
{
    use HasFactory,
        HasNiceUlids;

    protected $table = 'media_assets';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transformations()
    {
        return $this->hasMany(Transformation::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function originalTransformation()
    {
        return $this->transformations()
            ->where('type', 'original')
            ->first();
    }

    public static function sanitizeFilename(string $unsafeFilename): string
    {
        return str_replace([' ', '"', "'", '&', '/', '\\', '?', '#'], '_', $unsafeFilename);
    }

    public function getExtensionAttribute()
    {
        $filename = basename($this->key);

        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    // normalize name
    public function href(string $transformType = 'original'): string
    {
        $extension = pathinfo($this->original_name, PATHINFO_EXTENSION);

        // if the transform doesnt exist, we should generate it

        $transformation = $this
            ->transformations()
            ->where('type', $transformType)
            ->first();

        if (is_null($transformation)) {
            // note: should exist for next time
            $this->generateTransformation($transformType);

            $transformation = $this->originalTransformation();
        }

        if (is_null($transformation)) {
            // original couldnt be found for some reason
        }

        if ($this->visibility === 'private') {
            return URL::temporarySignedRoute('assets.show', now()->addMinutes(10), [
                'asset' => $this->getRouteKey(),
                'transformation' => $transformation ?? $transformType,
                'extension' => $extension,
            ]);
        }

        return route('assets.show', [
            'asset' => $this->getRouteKey(),
            'transformation' => $transformation ?? $transformType,
            'extension' => $extension,
        ]);
    }

    public function assetPath()
    {
        return match ($this->disk) {
            's3' => $this->s3Path(),
            'local' => asset('storage/'.$this->key),
            default => null,
        };
    }

    private function generateTransformation(string $transformType)
    {
        //
    }

    private function s3Path()
    {
        $client = StoreAssetControllerAction::storageClient();

        $command = $client->getCommand('getObject', [
            'Bucket' => $this->bucket,
            'Key' => $this->key,
        ]);

        $request = $client->createPresignedRequest($command, '+20 minutes');

        return strval($request->getUri());
    }
}
