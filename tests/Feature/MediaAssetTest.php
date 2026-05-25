<?php

namespace Tests\Feature;

use App\Models\Catalog\ProductFamily;
use App\Models\Media\Asset;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    public function test_attaching_media_to_model()
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;
        $asset = Asset::factory()
            ->for($org)
            ->for($user)
            ->icon()
            ->create();

        $family = ProductFamily::factory()
            ->for($org)
            ->create();

        $family->attachAsset($asset, 'icon');
        $this->assertNotNull($family->icon);
        $this->assertEquals($asset->id, $family->icon->id);
        $this->assertDatabaseHas('media_attachments', [
            'asset_id' => $asset->id,
            'attachable_id' => $family->id,
            'attachable_type' => 'product_family',
            'type' => 'icon',
        ]);
    }

    public function test_upload_image_asset_local(): void
    {
        $user = $this->createUser();

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg');
        $file->sizeToReport = 1024 * 1024 * 2;

        $upload = [
            'original_name' => 'company_logo.png',
            'visibility' => 'public-read',
            'content_type' => 'image/jpeg',
            'file_size' => $file->getSize(),
            'cache_control' => 'max-age=31536000',
        ];
        $createUploadUrlRes = $this->postJson('/v1/media/assets', $upload);
        $createUploadUrlRes->assertCreated();

        $asset = Asset::retrieve($createUploadUrlRes->json('asset.id'));
        $this->assertNotNull($asset);

        $uploadRes = $this->put($createUploadUrlRes->json('upload_url'), [
            'file' => $file,
            'key' => $createUploadUrlRes->json('form.key'),
        ]);
        $uploadRes->assertCreated();
        $this->assertNull($uploadRes->exception);

        $getFileRes = $this->get($asset->href());
        $getFileRes->assertStatus(302);

        $this->assertFileExists(storage_path('app/public/'.$asset->key));

        $this->assertNotNull($getFileRes->headers->get('Location'));

        $family = ProductFamily::factory()
            ->for($user->currentOrganization)
            ->create();

        $family->attachAsset($asset, 'icon');

        $this->actingAs($user);

        $getFamilyRes = $this->getJson(route('api/catalog.product_families.show', $family));
        $getFamilyRes->assertOk();

        $this->assertNotNull($getFamilyRes->json('icon.href'));
    }

    // test with s3 adapter
    public function test_upload_image_asset_s3(): void
    {
        $user = $this->createUser();

        $this->actingAs($user);

        config()->set('filesystems.media', 's3');

        $file = UploadedFile::fake()->image('avatar.jpg');
        $file->sizeToReport = 1024 * 1024 * 2;

        // env() is boot-time-only in laravel 11 — push into the config repo
        // directly so the runtime read picks up the fakes.
        config()->set('filesystems.disks.s3.key', 'a');
        config()->set('filesystems.disks.s3.secret', 'b');
        config()->set('filesystems.disks.s3.token', 'c');
        config()->set('filesystems.disks.s3.url', config('app.url'));

        $upload = [
            'original_name' => 'company_logo.png',
            'visibility' => 'public-read',
            'content_type' => 'image/jpeg',
            'file_size' => $file->getSize(),
            'cache_control' => 'max-age=31536000',
        ];
        $createUploadUrlRes = $this->postJson('/v1/media/assets', $upload);
        $createUploadUrlRes->assertCreated();

        $asset = Asset::retrieve($createUploadUrlRes->json('asset.id'));
        $this->assertNotNull($asset);

        $uri = new Uri($createUploadUrlRes->json('upload_url'));

        $this->assertSame('/'.$asset->key, $uri->getPath());
        $query = Query::parse($uri->getQuery());

        //        $this->assertSame('public-read', $query['x-amz-acl']);
        $this->assertSame('c', $query['X-Amz-Security-Token']);

        $this->assertNotNull($asset->assetPath());
    }
}
