<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Asset upload safety:
 *   - extensions are allowlisted (no `.php`, `.htaccess`, `.svg`, etc.)
 *   - client-supplied `bucket` is ignored (was an SSRF-ish smuggling vector)
 *   - the local-disk upload writer refuses paths that escape `assets/`
 */
class MediaUploadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assets_post_with_disallowed_extension_returns_422(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->postJson('/v1/media/assets', [
            'original_name' => 'evil.php',
            'content_type' => 'application/x-php',
            'file_size' => 1024,
        ])->assertStatus(422);
    }

    public function test_assets_post_with_allowed_extension_succeeds(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->postJson('/v1/media/assets', [
            'original_name' => 'logo.png',
            'content_type' => 'image/png',
            'file_size' => 2048,
        ])->assertStatus(201);
    }

    public function test_client_supplied_bucket_is_ignored(): void
    {
        Sanctum::actingAs($this->createUser());

        $envBucket = $_ENV['MEDIA_FILESYSTEM_BUCKET'] ?? config('filesystems.disks.s3.bucket');

        $response = $this->postJson('/v1/media/assets', [
            'original_name' => 'logo.png',
            'content_type' => 'image/png',
            'file_size' => 2048,
            'bucket' => 'attacker-controlled-bucket',
        ])->assertStatus(201);

        $this->assertSame($envBucket, $response->json('bucket'),
            'asset must persist with the env bucket, not the request-supplied one');
        $this->assertNotSame('attacker-controlled-bucket', $response->json('bucket'));
    }

    public function test_upload_endpoint_rejects_paths_outside_assets(): void
    {
        Sanctum::actingAs($this->createUser());

        // Signed URL with a path that tries to escape the assets/ subtree.
        // The signature covers `path` so external manipulation is normally
        // blocked, but we test the defence-in-depth path filter directly.
        $url = URL::temporarySignedRoute('api/media.uploads.store', now()->addMinutes(5), [
            'key' => 'asset',
            'path' => '../../config/app.php',
        ]);

        $this->call('PUT', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'payload')->assertStatus(400);
    }

    public function test_upload_endpoint_rejects_paths_with_backslashes(): void
    {
        Sanctum::actingAs($this->createUser());

        $url = URL::temporarySignedRoute('api/media.uploads.store', now()->addMinutes(5), [
            'key' => 'asset',
            'path' => 'assets\\..\\winshell',
        ]);

        $this->call('PUT', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], 'payload')->assertStatus(400);
    }
}
