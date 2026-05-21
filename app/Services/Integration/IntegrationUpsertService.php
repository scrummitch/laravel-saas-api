<?php

namespace App\Services\Integration;

use App\Database\Model;
use App\Models\Billing\BillingProvider;
use App\Services\BaseService;
use Illuminate\Support\Arr;
use Laravel\Socialite\AbstractUser;
use SocialiteProviders\Manager\OAuth2\User;

class IntegrationUpsertService extends BaseService
{
    public function fromSocialite(AbstractUser $user, array $state, string $provider): Model
    {
        $model = match ($provider) {
            'stripe', 'stripe_test' => $this->fromStripe($user, $state, $provider),
            default => throw new \InvalidArgumentException("Unsupported provider: $provider"),
        };

        return $model;
    }

    protected function fromStripe(User $user, array $state, string $provider): BillingProvider
    {
        $environment = $user->accessTokenResponseBody['livemode'] ?? false ? 'live' : 'test';

        /* @var BillingProvider $billingProvider */
        $billingProvider = BillingProvider::query()
            ->updateOrCreate([
                'organization_id' => Arr::get($state, 'organization_id'),
                'lookup_key' => $user->getId(),
                'type' => $provider,
            ], [
                'environment' => $environment,
                'name' => $user->nickname. '('.$environment.')',
                'secret' => $user->token,
                'config' => array_merge(
                    ['access_token' => $user->accessTokenResponseBody],
                    ['user_raw' => $user->getRaw()],
                ),
            ]);

        $organization = $billingProvider->organization;

        if ($billingProvider->environment === 'live' && is_null($organization->live_billing_provider_id)) {
            $billingProvider->organization->live_billing_provider_id = $billingProvider->id;

            $liveClient = $organization->clients()->where('environment', 'live')->first();

            if ($liveClient && is_null($liveClient->billing_provider_id)) {
                $liveClient->billing_provider_id = $billingProvider->id;
                $liveClient->save();
            }
        }

        if ($billingProvider->environment === 'test' && is_null($organization->test_billing_provider_id)) {
            $organization->test_billing_provider_id = $billingProvider->id;

            $testClient = $organization->clients()->where('environment', 'test')->first();

            if ($testClient && is_null($testClient->billing_provider_id)) {
                $testClient->billing_provider_id = $billingProvider->id;
                $testClient->save();
            }
        }

        if ($organization->isDirty()) {
            $organization->save();
        }

        $billingProvider->import();

        return $billingProvider;
    }
}
