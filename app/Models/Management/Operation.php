<?php

namespace App\Models\Management;

use App\Database\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Bus;

/**
 * @property string $organization_id
 * @property int $model_id
 * @property string $model_type
 * @property string $batch_id
 * @property Model $model
 * @property string $name
 * @property string $description
 * @property string $output
 * @property string $status [pending, running, failed, finished, cancelled]
 * @property Carbon|null $failed_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 */
class Operation extends Model
{
    use HasFactory;

    protected $table = 'mgmt_operations';

    protected $casts = [
        'output' => 'json',
        'metadata' => 'json',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public static function forBatch(?\Illuminate\Bus\Batch $batch)
    {
        return self::query()
            ->where('batch_id', $batch->id);
    }

    public function isStuck(): bool
    {
        $batch = Bus::findBatch($this->batch_id);

        return $this->status === 'running' && $this->updated_at->addMinutes(5)->isPast();
    }

    public function retry(): void
    {
        Operation::query()
            ->where([
                'model_type' => $this->model_type,
                'model_id' => $this->model_id,
            ])
            ->where('id', '!=', $this->id)
            ->delete();

        $this->cancel();
        $this->model->import(true);
    }

    public function cancel()
    {
        $batch = Bus::findBatch($this->batch_id);

        if (! $batch->finished()) {
            $batch->cancel();
            $this->status = 'cancelled';
            $this->cancelled_at = now();
            $this->save();
        }

        if ($batch->finished()) {
            $this->status = 'finished';
            $this->finished_at = now();
            $this->save();
        }
    }

    public function calculateRuntime(): ?int
    {
        if ($this->finished_at) {
            return $this->finished_at->diffInSeconds($this->created_at);
        }

        return now()->diffInSeconds($this->created_at);
    }
}
