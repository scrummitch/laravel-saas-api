<?php

namespace App\Http\Controllers\Hooks;

use App\Http\Controllers\Controller;
use App\Models\Billing\BillingProvider;
use App\Services\Sync\Stripe\StripeEventProcessor;
use Illuminate\Http\Request;
use Stripe\Webhook;

class ProcessStripeHookController extends Controller
{
    public function __invoke(Request $request)
    {
        $json = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $env = $request->json('livemode') ? 'stripe' : 'stripe_test';
        $account = $request->json('account');

        if (empty($account) && app()->environment('local')) {
            $account = 'acct_1KoGqoFmvUKqVS2H';
        }

        $secret = config('services.'.$env.'.webhook_secret');

        try {
            $event = Webhook::constructEvent($json, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            logger()->warning('UnexpectedValueException', [
                'message' => $e->getMessage(),
                'account' => $account,
            ]);

            return;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            logger()->error('SignatureVerificationException', [
                'message' => $e->getMessage(),
                'sigHeader' => $sigHeader,
                'account' => $account,
                'event_id' => $request->json('id'),
            ]);

            return;
        }

        $billingProviders = BillingProvider::query()
            ->where('lookup_key', $account)
            ->where('environment', $event->livemode ? 'live' : 'test')
            ->get();

        if ($billingProviders->isEmpty()) {
            logger()->error('No billing provider found', [
                'account' => $event->account,
                'environment' => $event->livemode ? 'live' : 'test',
                'event_id' => $event?->id,
                'event_type' => $event->type,
                'lookup_key' => $event->account,
            ]);

            return;
        }

        logger()->info(logname(), [
            'account' => $event->account,
            'environment' => $event->livemode ? 'live' : 'test',
            'event_id' => $event?->id,
            'event_type' => $event->type,
            'lookup_key' => $event->account,
            'providers_count' => count($billingProviders),
        ]);

        foreach ($billingProviders as $billingProvider) {
            $processor = new StripeEventProcessor($billingProvider);
            $processor->handle($event);
        }

        return response()->json();
    }
}
