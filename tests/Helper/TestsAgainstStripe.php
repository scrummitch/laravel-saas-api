<?php

namespace Tests\Helper;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\PaymentMethod;

trait TestsAgainstStripe
{
    use WithFaker;

    protected function postStripeWebhookEvent(array $data): TestResponse
    {
        config('services.stripe.webhook_secret', 'abc123');

        $ts = Carbon::now()->utc()->timestamp;
        $payload = json_encode($data);
        $headers = [
            'Stripe-Signature' => implode(',', [
                't='.$ts,
                'v1='.hash_hmac('sha256', $ts.'.'.$payload, config('services.stripe_test.webhook_secret')),
            ]),
        ];

        return $this->postJson('/hooks/stripe', $data, $headers);
    }

    protected function mockStripe(array $responses)
    {
        $httpClient = $this->mock(ClientInterface::class);
        $index = 0;

        $httpClient
            ->expects('request')
            ->times(count($responses))
            ->andReturnUsing(function ($method, $url, $options) use ($responses, &$index) {
                logger()->info(logname('intercept'), [
                    'method' => $method,
                    'url' => $url,
                    'options' => $options,
                ]);

                return $responses[$index++];
            });

        ApiRequestor::setHttpClient($httpClient);
    }

    public function makeStripePaymentMethodList()
    {
        return [
            'object' => 'list',
            'url' => '/v1/payment_methods',
            'has_more' => false,
            'data' => [$this->makeStripePaymentMethod()->toArray()],
        ];
    }

    public function makeStripePaymentMethod($options = [])
    {
        return PaymentMethod::constructFrom(
            array_merge(
                [
                    'id' => "pm_{$this->faker->md5()}",
                    'object' => 'payment_method',
                    'billing_details' => [
                        'address' => [
                            'city' => $this->faker->city(),
                            'country' => $this->faker->country(),
                            'line1' => $this->faker->streetAddress(),
                            'line2' => null,
                            'postal_code' => $this->faker->postcode(),
                            'state' => $this->faker->state(),
                        ],
                    ],
                    'card' => [
                        'brand' => 'visa',
                        'checks' => [
                            'address_line1_check' => null,
                            'address_postal_code_check' => null,
                            'cvc_check' => 'pass',
                        ],
                        'country' => $this->faker->country(),
                        'exp_month' => $this->faker->numberBetween(1, 12),
                        'exp_year' => $this->faker
                            ->dateTimeBetween('now', '+10 years')
                            ->format('Y'),
                        'fingerprint' => $this->faker->regexify(
                            '[A-Za-z0-9]{16}'
                        ),
                        'funding' => 'credit',
                        'generated_from' => null,
                        'last4' => '4242',
                        'networks' => [
                            'available' => ['visa'],
                            'preferred' => null,
                        ],
                        'three_d_secure_usage' => [
                            'supported' => true,
                        ],
                        'wallet' => null,
                    ],
                    'created' => Carbon::now()->timezone('UTC')->timestamp,
                    'customer' => $this->faker->md5(),
                    'livemode' => false,
                    'metadata' => [
                        'order_id' => "{$this->faker->numerify('#########')}",
                    ],
                    'type' => 'card',
                ],
                $options
            )
        );
    }
}
