<?php

namespace App\Observers;

use App\Models\Account\Signal;
use App\Models\Intelligence\Activity;

class SignalObserver
{
    /**
     * Handle the Signal "created" event.
     */
    public function created(Signal $signal): void
    {
        if (is_null($signal)) {
            return;
        }

        $activity = Activity::query()
            ->where(['customer_id' => $signal->customer_id])
            ->whereBetween('last_interaction_at', [
                now()->subMinutes(5),
                now(),
            ])
            ->latest()
            ->first();

        if (is_null($activity)) {
            return;
        }

        $signal->activity()->associate($activity);
        $signal->save();
    }

    /**
     * Handle the Signal "updated" event.
     */
    public function updated(Signal $signal): void
    {
        //
    }

    /**
     * Handle the Signal "deleted" event.
     */
    public function deleted(Signal $signal): void
    {
        //
    }

    /**
     * Handle the Signal "restored" event.
     */
    public function restored(Signal $signal): void
    {
        //
    }

    /**
     * Handle the Signal "force deleted" event.
     */
    public function forceDeleted(Signal $signal): void
    {
        //
    }
}
