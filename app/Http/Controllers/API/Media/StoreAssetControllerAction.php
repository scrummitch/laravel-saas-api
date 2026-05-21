<?php

namespace App\Http\Controllers\API\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Resources\Api\AssetApiResource;
use App\Models\Media\Asset;
use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/**
 * Create a new Asset and return a signed URL for uploading the file.
 */
class StoreAssetControllerAction extends Controller
{
    public function __invoke(StoreAssetRequest $request)
    {
        $asset = new Asset;
        $asset->organization_id = $request->user()->organization_id;
        $asset->user_id = $request->user()->id;
        $asset->ulid = $asset->newUniqueId();

        $filename = $request->get('original_name');
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $transformId = Str::uuid()->getHex()->toString();

        $key = implode('/', [
            'assets',
            $asset->getRouteKey(),
            $transformId.'.'.$extension,
        ]);
        $signedRequest = $this->createSignedRequest($request, $key);

        $asset->disk = config('filesystems.media');
        $asset->current_state = 'pending';
        $asset->key = $key;
        $asset->name = $asset->sanitizeFilename($filename);
        $asset->original_name = $filename;
        $asset->bucket = $request->input('bucket') ?: $_ENV['MEDIA_FILESYSTEM_BUCKET'];
        $asset->content_type = $request->input('content_type') ?: 'application/octet-stream';
        $asset->size = $request->input('file_size');
        $asset->visibility = $request->input('visibility') ?: $this->defaultVisibility();

        $asset->save();

        // create the "og" transform which we use to load until we want to add optimised versions
        $asset->transformations()->create([
            'type' => 'original',
            'name' => 'original',
            'options' => null,
            'uuid' => $transformId,
        ]);

        $uri = $signedRequest->getUri();

        return response()->json([
            'upload_url' => $uri->getScheme().'://'.$uri->getAuthority().$uri->getPath().'?'.$uri->getQuery(),
            'old_upload_url' => $uri,
            'ulid' => $asset->ulid,
            'bucket' => $asset->bucket,
            'key' => $key,
            'asset' => new AssetApiResource($asset),
            'headers' => $this->headers($request, $signedRequest),
        ], 201);
    }

    protected function createCommand(Request $request, S3Client $client, $bucket, $key)
    {
        return $client->getCommand('putObject', array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            //            'ACL' => $request->input('visibility') ?: $this->defaultVisibility(),
            'ContentType' => $request->input('content_type') ?: 'application/octet-stream',
            'CacheControl' => $request->input('cache_control') ?: null,
            'Expires' => $request->input('expires') ?: null,
        ]));
    }

    protected function headers(Request $request, $signedRequest): array
    {
        return array_merge(
            $signedRequest->getHeaders(),
            [
                'Content-Type' => $request->input('content_type') ?: 'application/octet-stream',
            ]
        );
    }

    public static function storageClient(): S3Client
    {
        $config = [
            'region' => config('filesystems.disks.s3.region', $_ENV['AWS_DEFAULT_REGION']),
            'version' => 'latest',
            'signature_version' => 'v4',
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', false),
        ];

        if (! isset($_ENV['AWS_LAMBDA_FUNCTION_VERSION'])) {
            $config['credentials'] = array_filter([
                'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
                'token' => $_ENV['AWS_SESSION_TOKEN'] ?? null,
            ]);

            if (array_key_exists('AWS_URL', $_ENV) && ! is_null($_ENV['AWS_URL'])) {
                $config['url'] = $_ENV['AWS_URL'];
                $config['endpoint'] = $_ENV['AWS_URL'];
            }
        }

        // config from metadata api
        if (isset($_ENV['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI'])) {
            $config['credentials'] = CredentialProvider::defaultProvider();
        }

        return new S3Client($config);
    }

    private function defaultVisibility(): string
    {
        //        return 'private';
        return 'public-read';
    }

    private function createSignedRequest(Request $request, string $key): RequestInterface
    {
        $disk = config('filesystems.media');

        return match ($disk) {
            's3' => $this->createS3SignedRequest($request, $key),
            'local' => $this->createLocalSignedRequest($request, $key),
            default => throw new \InvalidArgumentException('Unsupported disk: '.$disk),
        };

    }

    public function createS3SignedRequest(Request $request, string $key): RequestInterface
    {
        $client = $this->storageClient();

        $bucket = $request->input('bucket') ?: $_ENV['MEDIA_FILESYSTEM_BUCKET'];
        $expiresAfter = 5;

        return $client->createPresignedRequest(
            $this->createCommand($request, $client, $bucket, $key),
            sprintf('+%s minutes', $expiresAfter)
        );
    }

    private function createLocalSignedRequest(Request $request, string $key): RequestInterface
    {
        $expiresAfter = config('vapor.signed_storage_url_expires_after', 5);

        $url = URL::temporarySignedRoute(
            'api/media.uploads.store',
            $expiresAfter,
            array_merge(['key' => 'asset'], ['path' => $key])
        );

        return new \GuzzleHttp\Psr7\Request('post', $url);
    }
}
