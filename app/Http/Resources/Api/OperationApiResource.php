<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class OperationApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $batch = Bus::findBatch($this->batch_id);

        $failures = DB::table('failed_jobs')
            ->whereIn('uuid', $batch->failedJobIds)
            ->get();

        return [
            'id' => $this->getRouteKey(),
            'object' => 'operation',
            'batch_id' => $this->batch_id,

            'meta' => [
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
            ],

            'failed_jobs' => $failures
                ->map(function ($failure) {
                    return [
                        'id' => $failure->id,
                        'uuid' => $failure->uuid,
                        'connection' => $failure->connection,
                        'queue' => $failure->queue,
                        'payload' => json_decode($failure->payload, true),
                        'exception' => json_decode($failure->exception, true),
                        'failed_at' => $failure->failed_at,
                    ];
                }),

            'metadata' => $this->metadata,

            'name' => $this->name,
            'description' => $this->description,
            'output' => $this->output,
            'status' => $this->status,
            'failed_at' => $this->failed_at,
            'finished_at' => $this->finished_at,
            'cancelled_at' => $this->cancelled_at,

            'runtime' => $this->calculateRuntime(),
        ];
    }
}
