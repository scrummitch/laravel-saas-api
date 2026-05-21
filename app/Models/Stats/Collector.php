<?php

namespace App\Models\Stats;

use App\Billing\Currency;
use App\Billing\ISO4217;
use App\Database\Model;
use App\Models\Account\Agent;
use App\Models\Client;
use App\Models\Intelligence\Activity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Intl\Countries;

/**
 * @property int    $id
 * @property string $ulid
 * @property string $client_id
 * @property string $anonymous_id
 * @property string $agent_id
 * @property string $country
 * @property string $origin
 * @property string $browser
 * @property string $created_at
 * @property Client $client
 * @property Agent $agent
 */
class Collector extends Model
{
    use HasFactory,
        HasUuids;

    protected $table = 'stats_collectors';

    protected $guarded = [];

    const UPDATED_AT = null;

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function countryName(): ?string
    {
        return $this->country ? Countries::getName($this->country) : null;
    }

    public function countryFlag(): string
    {
        return (string) preg_replace_callback(
            '/./',
            static fn (array $letter) => mb_chr(ord($letter[0]) % 32 + 0x1F1E5),
            $this->country
        );
    }

    public function inferredCurrency(): ?Currency
    {
        return ISO4217::forCountryCode($this->country);
    }
}
