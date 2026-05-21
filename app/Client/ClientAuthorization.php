<?php

namespace App\Client;

use App\Integration\Connectors\StripeConnector;
use App\Models\Account\Agent;
use App\Models\Account\Association;
use App\Models\Account\Customer;
use App\Models\Client;
use App\Models\Management\Organization;
use App\Models\Stats\Collector;
use App\Models\Twin;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\Query;
use Illuminate\Database\Eloquent\Builder;

class ClientAuthorization
{
    public ?Client $client = null;

    public ?Agent $agent = null;

    public ?Association $association = null;

    public ?Customer $customer = null;

    public ?Collector $collector = null;
    public ?\stdClass $payload = null;

    public static function parseJwtString($jwt)
    {
        $tks = \explode('.', $jwt);

        if (\count($tks) !== 3) {
            return null;
        }

        [$headb64, $bodyb64, $cryptob64] = $tks;

        $headerRaw = JWT::urlsafeB64Decode($headb64);
        $header = JWT::jsonDecode($headerRaw);

        $kid = data_get($header, 'kid');

        return [$jwt, $kid, $header];
    }

    public function __construct(?string $jwtString)
    {
        $request = request();
        [$jwt, $kid, $header] = $this->parseJwtString($jwtString);

        $client = Client::retrieve($request->input('client', $kid));
        $client?->loadMissing(['organization', 'billingProvider']);

        $this->client = $client;

        if (! $client) {
            logger()->error(logname('fail'), [
                'message' => 'no kid provided',
                'jwt' => $jwt,
            ]);

            throw new Exception('Invalid client id');
        }

        if ($request->filled('collector') && ! is_null($client)) {
            $this->collector = Collector::query()
                ->where('client_id', $client?->id)
                ->where('uuid', $request->input('collector'))
                ->first();
        }

        if (empty($jwt)) {
            return;
        }

        $organization = $client->organization;

        $headers = new \stdClass;
        $key = new Key($client->getSecretStr(), data_get($header, 'alg', 'HS256'));

        $this->payload = $payload = JWT::decode($jwt, $key, $headers);
        $isSandbox = data_get($payload, 'aud') === 'sandbox';

        $sub = data_get($payload, 'sub');
        $grp = data_get($payload, 'grp');

        $this->agent = $agent = Agent::query()
            ->firstOrCreate([
                'organization_id' => $client->organization->id,
                'lookup_key' => $sub,
            ], [
                'is_sandbox_user' => $isSandbox,
            ]);


        if (isset($payload->customer)) {
            try {
                $customer = Customer::query()
                    ->with(['billingProvider'])
                    ->where('reference_id', $payload->customer)
                    ->first();
            } catch (Exception $e) {
                $customer = $this->findOrCreateCustomer($client, $payload->customer, $organization, $isSandbox);

                if (! $customer) {
                    throw new Exception('Invalid customer');
                }
            }
        } else {
            $customer = $this->findDefaultCustomer($grp);
        }

        if ($this->collector && is_null($this->collector->agent_id) && ! is_null($agent)) {
            $this->collector->agent_id = $agent->id;
            $this->collector->save();
        }

        if ($grp || $customer) {
            $association = Association::query()
                ->where(function (Builder $query) use ($grp, $customer, $sub) {
                    return $query
                        ->where(['key' => $grp ?? optional($customer)->id ?? $sub])
                        ->orWhere(['key' => null]);
                })
                ->updateOrCreate([
                    'agent_id' => $agent->id,
                    'organization_id' => $client->organization->id,
                    'customer_id' => optional($customer)->id,
                ]);
        } else {
            $association = Association::query()
                ->where([
                    'key' => null,
                    'agent_id' => $agent->id,
                    'organization_id' => $client->organization->id,
                ])
                ->first();

            if ($association?->customer_id !== optional($customer)->id) {
                $customer = Customer::query()
                    ->find($association->customer_id);
            }
        }

        $this->agent = $agent;
        $this->association = $association;
        $this->customer = $customer;
    }

    protected function findDefaultCustomer(?string $grp = null): ?Customer
    {
        $billing = $this->client->billingProvider ?: $this->client->organization->liveBillingProvider;

        if (is_null($billing)) {
            return null;
        }

        $query = Association::query()
            ->where('agent_id', $this->agent->id)
            ->where('account_associations.organization_id', $billing->organization_id)
            ->join('account_customers', 'account_customers.id', '=', 'account_associations.customer_id')
            ->where('account_customers.billing_provider_id', $billing->id);

        if ($grp) {
            $query->where('account_associations.key', $grp);
        }

        $association = $query->first();

        return $association?->customer;
    }

    private function findOrCreateCustomer(Client $client, string $customerId, Organization $org, $isSandbox = false): ?Customer
    {
        $billing = $client->billingProvider ?: $org->liveBillingProvider;

        $stripeClient = (new StripeConnector($billing))->getStripeClient();
        $stripeCustomer = $stripeClient->customers->retrieve($customerId);

        if ($stripeCustomer) {
            $customer = Customer::query()
                ->firstOrNew([
                    'organization_id' => $org->id,
                    'billing_provider_id' => $billing->id,
                    'reference_id' => $customerId,
                ]);

            $customer->fill([
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
                'reference_id' => $customerId,
            ]);

            $customer->save();

            Twin::unguard();
            $twin = Twin::query()
                ->updateOrCreate([
                    'reference_id' => $stripeCustomer->id,
                    'connector_id' => $billing->id,
                    'connector_type' => 'billing_provider',
                    'organization_id' => $billing->organization_id,
                ], Twin::fromStripeObject($stripeCustomer)->toArray());
            Twin::reguard();

            $twin->link($customer);

            return $customer;
        }

        return null;
    }

    public function cartToken(): ?string
    {
        $token = request()->header('Plandalf-Cart');

        if (! $token && !$this->agent) {
            abort(401, 'Unauthorized');
        }

        if (!$token) {
            return null;
        }

        [$c, $q] = explode('?', $token);

        $query = Query::parse($q);
        $s = $query['sig'];

        $signature = hash_hmac('sha256', $c, config('app.key'));

        if ($s !== $signature) {
            abort(401, 'Unauthorized');
        }

        if (! hash_equals($s, $signature)) {
            abort(401, 'Unauthorized');
        }

        return $c;
    }
}
