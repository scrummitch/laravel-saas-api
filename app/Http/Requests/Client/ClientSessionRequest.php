<?php

namespace App\Http\Requests\Client;

use App\Client\ClientAuthorization;
use App\Database\DeviceDetectorCache;
use App\Models\Stats\Collector;
use DeviceDetector\DeviceDetector;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Exception\GeoIp2Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Sentry\Tracing\SpanContext;

class ClientSessionRequest extends FormRequest
{
    protected ?Collector $_collector;

    public function auth(): ClientAuthorization
    {
        return app(ClientAuthorization::class);
    }

    public function collector(): Collector
    {
        return $this->_collector ??= $this->auth()->collector ?? $this->findCollector();
    }

    public function client()
    {
        return $this->auth()->client;
    }

    private function findCollector(): Collector
    {
        $collectorId = $this->get('collector');
        $auth = $this->auth();

        if ($collectorId) {
            $collector = Collector::query()
                ->firstOrNew([
                    'uuid' => $collectorId,
                    'client_id' => $auth->client?->getKey(),
                ]);
        } elseif ($auth->agent) {
            $collector = Collector::query()
                ->where([
                    'agent_id' => $auth->agent?->id,
                    'client_id' => $auth->client?->getKey(),
                ])
                ->firstOrNew();
        } else {
            $collector = new Collector;
        }

        // if collector agent does not match the agent in auth then we need to make a new one
        if ($collector->exists && $auth->agent && $collector->agent_id !== $auth->agent->id) {
            $collector = new Collector;
        }

        if (! $collector->exists) {
            $spanContext = SpanContext::make()
                ->setOp(logname('device-detection'))
                ->setDescription('Detects the collector device and location');

            $v = \Sentry\trace(function () use ($auth) {
                $dd = new DeviceDetector($this->userAgent());
                $dd->setCache(new DeviceDetectorCache);
                $dd->skipBotDetection();
                $dd->parse();

                $cityDbReader = new Reader(resource_path('app/GeoLite2-Country.mmdb'));
                try {
                    $record = $cityDbReader->country($this->ip());
                } catch (GeoIp2Exception $e) {
                    $record = null;
                }

                return [
                    'client_id' => $auth->client?->getKey(),
                    'agent_id' => $auth->agent?->getKey(),
                    'origin' => $this->header('Origin', 'unknown'),
                    'country' => $record?->country->isoCode,
                    'os' => Arr::get($dd->getOs(), 'short_name'),
                    'browser' => Arr::get($dd->getClient(), 'short_name'),
                    'created_at' => now(),
                ];
            }, $spanContext);

            $collector = Collector::query()->create($v);
        }

        return $collector;
    }
}
