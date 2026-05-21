<?php

namespace App\Models;

use App\Models\Media\Asset;
use App\Models\Media\Attachment;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

trait HasAttachments
{
    protected function singleAttachment(string $type): HasOneThrough
    {
        return $this->hasOneThrough(
            Asset::class,
            Attachment::class,
            'attachable_id',
            'id',
            'id',
            'asset_id'
        )->where('media_attachments.attachable_type', $this->getMorphClass())
            ->where('media_attachments.type', $type);
    }

    protected function multiAttachment(string $type): MorphToMany
    {
        return $this
            ->morphToMany(Asset::class, 'attachable', 'media_attachments', 'attachable_id', 'asset_id')
            ->where('media_attachments.type', $type);
    }

    public function assets(): MorphToMany
    {
        return $this
            ->morphToMany(Asset::class, 'attachable', 'media_attachments', 'attachable_id', 'asset_id');
    }

    public function attachAsset(Asset $asset, string $type): Attachment
    {
        $a = [
            'asset_id' => $asset->id,
            'type' => $type,
            'attachable_id' => $this->id,
            'attachable_type' => $this->getMorphClass(),
        ];

        $attachment = Attachment::create($a);

        return $attachment;
    }

    /**
     * @param  Collection<Asset>  $assets
     * @return void
     */
    public function syncAttachments(string $type, Collection $assets)
    {
        $this->assets()->wherePivot('type', $type)->detach();

        $assets->each(function ($asset) use ($type) {
            $this->attachAsset($asset, $type);
        });
    }
}
