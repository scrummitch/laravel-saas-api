<?php

namespace App\Services\Billing;

use App\Jobs\ConfigureInitialPricingSchemeJob;
use App\Jobs\Services\Stripe\EstimateStripeImportJob;
use App\Jobs\Services\Stripe\ImportStripeCustomersJob;
use App\Jobs\Services\Stripe\ImportStripeProductsJob;
use App\Jobs\Services\Stripe\ImportStripeSubscriptionsJob;
use App\Models\Billing\BillingProvider;
use App\Models\Management\Operation;
use App\Services\BaseService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class ProviderInitialSyncService extends BaseService
{
    public function __invoke(BillingProvider $billing, $async = true)
    {
        // find existing pending operation
        $pendingOperation = $billing
            ->operations()
            ->whereIn('status', ['running', 'pending'])
            ->first();

        if ($pendingOperation && $pendingOperation->isStuck()) {
            logger()->info(logname('pending'), [
                'operation_id' => $pendingOperation->id,
            ]);
            $pendingOperation->delete();
        }

        $operation = new Operation;
        $operation->organization_id = $billing->organization_id;
        $operation->name = 'Stripe Account Import';
        $operation->status = 'pending';
        $operation->description = 'Import: '.$billing->name;

        $jobs = [
            new EstimateStripeImportJob($billing),
            new ImportStripeProductsJob($billing),
            new ConfigureInitialPricingSchemeJob($billing),
            new ImportStripeCustomersJob($billing),
            new ImportStripeSubscriptionsJob($billing),
        ];

        if (app()->runningUnitTests()) {
            foreach ($jobs as $job) {
                $job->handle();
            }

            $operation->status = 'finished';
        } else {
            /* @var PendingBatch $pendingBatch */
            $pendingBatch = Bus::batch([$jobs])
                ->progress(function (Batch $batch) {
                DB::table('mgmt_operations')
                    ->where('batch_id', $batch->id)
                    ->update([
                        'progress' => $batch->progress(),
                        'status' => 'running',
                    ]);
            })->then(function (Batch $batch) {
                // All jobs completed successfully...
                DB::table('mgmt_operations')
                    ->where('batch_id', $batch->id)
                    ->update([
                        'progress' => $batch->progress(),
                        'status' => 'finished',
                        'failed_at' => null,
                    ]);
            })->catch(function (Batch $batch, \Throwable $e) {
                // First batch job failure detected...
                DB::table('mgmt_operations')
                    ->where('batch_id', $batch->id)
                    ->update([
                        'progress' => $batch->progress(),
                        'status' => 'failed',
                        'failed_at' => now(),
                        'description' => $e->getMessage(),
                    ]);

                $this->updateModelState($batch, 'failed');

                $batch->cancel();
            })->finally(function (Batch $batch) {
                $this->updateModelState($batch, 'success');
            });

            if ($async) {
                $pendingBatch = $pendingBatch->onConnection(config('queue.default'));
            } else {
                $pendingBatch = $pendingBatch->onConnection('sync');
            }

            $batch = $pendingBatch->dispatch();

            $operation->batch_id = $batch->id;
        }

        $operation->model()->associate($billing);
        $operation->save();
    }

    private function updateModelState(Batch $batch, string $state)
    {
        $operation = Operation::where('batch_id', $batch->id)->first();
        if (! $operation) {
            return;
        }

        $model = $operation->model;

        $model->current_state = $state;
        $model->save();
    }
}
