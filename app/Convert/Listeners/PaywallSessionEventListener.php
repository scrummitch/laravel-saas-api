<?php

namespace App\Convert\Listeners;

use App\Convert\Events\PaywallConvertEvent;
use App\Convert\Events\PaywallEntryEvent;
use App\Convert\Events\PaywallSessionEventInterface;
use App\Convert\Events\PaywallStartEvent;
use Illuminate\Support\Facades\DB;

// todo : repalce with activities
class PaywallSessionEventListener
{
    public function __invoke(PaywallSessionEventInterface $event)
    {
        match($event->name()) {
            'entry' => $this->storeEntryEvent($event),
            'start' => $this->storeStartEvent($event),
            'convert' => $this->storeCompleteEvent($event),
        };

        $data = $event->data();

        DB::table('convert_paywall_events')
            ->insert([
                'session_id' => $event->session->id,
                'collector_id' => $event->session->collector_id,
                'name' => $event->name(),
                'data' => empty($data) ? null : json_encode($data),
                'created_at' => $event->timestamp,
            ]);
    }

    private function storeEntryEvent(PaywallEntryEvent $event)
    {
        if ($event->session->has_entered) {
            return;
        }

//        PaywallSession::unguarded(fn () => $event->session->update([
//            'has_entered' => true,
//            'entered_at' => $event->timestamp,
//            'last_interaction_at' => $event->timestamp,
//        ]));
    }

    private function storeStartEvent(PaywallStartEvent $event)
    {
        if ($event->session->has_started) {
            return;
        }

//        PaywallSession::unguarded(fn () => $event->session->update([
//            'has_started' => true,
//            'started_at' => $event->timestamp,
//            'last_interaction_at' => $event->timestamp,
//        ]));
    }

    private function storeCompleteEvent(PaywallConvertEvent $event): void
    {
        if ($event->session->has_completed) {
            return;
        }

//        PaywallSession::unguarded(fn () => $event->session->update([
//            'has_completed' => true,
//            'completed_at' => $event->timestamp,
//            'last_interaction_at' => $event->timestamp,
//        ]));
    }
}
