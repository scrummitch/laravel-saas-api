<?php

namespace App\Models\Media;

use App\Database\Model;

class Attachment extends Model
{
    protected $table = 'media_attachments';

    protected $fillable = ['asset_id', 'attachable_id', 'attachable_type', 'type'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
