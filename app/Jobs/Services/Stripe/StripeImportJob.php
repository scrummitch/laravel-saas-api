<?php

namespace App\Jobs\Services\Stripe;

use App\Integration\Connectors\StripeConnector;
use App\Jobs\QueueableJob;
use App\Models\Billing\BillingProvider;
use App\Models\Management\Operation;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Throwable;

abstract class StripeImportJob extends QueueableJob
{
    use Batchable;

    public StripeClient $stripeclient;

    public $timeout = 600;

    protected StripeConnector $connector;

    protected ?Operation $operation = null;

    public function __construct(public BillingProvider $billing)
    {
        $this->connector = new StripeConnector($billing);
        $this->stripeclient = $this->connector->getStripeClient();
    }

    public function operation(): ?Operation
    {
        return $this->operation ??= Operation::query()
            ->where('batch_id', $this->batchId)
            ->first();
    }

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error(get_class($this).'@failed', [
            'message' => $exception->getMessage(),
        ]);
    }

    protected function setMetadata(array|string $metadata, $value = null)
    {
        if (! $this->operation()) {
            return;
        }

        if (is_string($metadata)) {
            $metadata = [$metadata => $value];
        }

        $updates = collect($metadata)
            ->mapWithKeys(function ($value, $key) {
                return ["metadata->$key" => $value];
            });

        DB::table('mgmt_operations')
            ->where('id', $this->operation()->id)
            ->update($updates->toArray());
    }

    protected function cursorChunked($query, $chunkSize): \Generator
    {
        $cursor = $query->cursor();
        $chunk = [];

        foreach ($cursor as $item) {
            $chunk[] = $item;
            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            yield $chunk;
        }
    }

    protected function estimateTimeRemaining(int $totalItems, int $processedItems, int $secondsElapsed, int $multiplier = 2): string
    {
        if ($processedItems == 0) {
            return 'Estimating...';
        }

        $processingRate = $secondsElapsed / ($processedItems * $multiplier);
        $remainingItems = $totalItems - $processedItems;
        $estimatedTimeRemaining = $remainingItems * $processingRate;

        return gmdate('H:i:s', $estimatedTimeRemaining);
    }
}
