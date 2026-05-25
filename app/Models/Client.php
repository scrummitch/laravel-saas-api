<?php

namespace App\Models;

use App\Database\Model;
use App\Database\Traits\HasNiceUlids;
use App\Models\Billing\BillingProvider;
use App\Models\Convert\Flow;
use App\Models\Management\Operation;
use App\Models\Management\Organization;
use App\Models\Pricing\Scheme;
use App\Models\Publish\Rollout;
use Carbon\Carbon;
use DateTimeInterface;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Uid\BinaryUtil;
use Symfony\Component\Uid\Ulid;

/**
 * @property Organization $organization
 * @property string $ulid
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $secret
 * @property string $type -> 'web' | 'mobile' | 'server'
 * @property string $platform
 * @property Carbon|null $deleted_at
 * @property Collection $allowed_origins List of allowed origins for CORS
 * @property Collection $pending_origins List of origins pending approval
 * @property Collection<Flow> $workflows
 * @property Collection<Scheme> $schemes
 * @property BillingProvider $billingProvider
 * @property string $environment 'live' | 'test'
 * @property int $billing_provider_id
 * @property \Illuminate\Support\Collection $exclusion_rules
 * @property string $current_api_key
 */
class Client extends Model implements Authenticatable
{
    use HasFactory,
        \Illuminate\Auth\Authenticatable,
        HasApiTokens,
        HasNiceUlids,
        SoftDeletes;

    protected $casts = [
        'secret' => 'encrypted',
        'allowed_origins' => 'collection',
        'pending_origins' => 'collection',
        'exclusion_rules' => 'collection',
        'api_keys' => 'encrypted',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function operations(): MorphMany
    {
        return $this->morphMany(Operation::class, 'model');
    }

    public function schemes(): HasManyThrough
    {
        return $this->hasManyThrough(Scheme::class, Organization::class, 'id', 'organization_id', 'organization_id', 'id');
    }

    public function billingProvider(): BelongsTo
    {
        return $this->belongsTo(BillingProvider::class);
    }

    public function getSecretStr(): string
    {
        return hash('sha256', $this->secret);
    }

    public function tokens(): MorphMany
    {
        return $this->morphMany(Sanctum::$personalAccessTokenModel, 'tokenable')->chaperone();
    }

    public function rollouts(): HasMany
    {
        return $this->hasMany(Rollout::class);
    }

    public static function fromAgentJwt($jwt): ?Client
    {
        [$header] = explode('.', $jwt);

        return Cache::remember($header, Carbon::now()->addHour(), function () use ($header) {
            $header = json_decode(JWT::urlsafeB64Decode($header));

            return Client::retrieve(data_get($header, 'kid'));
        });
    }

    public function generateAgentJwt(string $uid, array $claims = []): string
    {
        return JWT::encode(array_merge(['sub' => $uid], $claims), $this->getSecretStr(), 'HS256', $this->getRouteKey());
    }

    public function regenerateSecret(): void
    {
        $this->secret = Str::random(40);
    }

    public function originList(): string
    {
        if (! $this->pending_origins || $this->pending_origins->isEmpty()) {
            return '';
        }

        return '('.$this->pending_origins?->implode(', ').')';
    }

    /**
     * Allowed origins for a single client, cached per ULID.
     *
     * Preflight CORS requests don't carry the JWT, but the SDK always
     * passes `?client=<ulid>` so we can still scope the trust check
     * to just that client — no bulk load.
     *
     * Short TTL accepts a minute of staleness after an admin edit;
     * the model events below also bust the entry on save/delete.
     */
    private const ORIGINS_CACHE_TTL = 60;

    public static function allowedOriginsFor(?string $kid): array
    {
        if ($kid === null || $kid === '') {
            return [];
        }

        // The SDK passes the public route key (e.g. `client_1CbPRy…`) which is
        // a model-prefixed base58 encoding of the raw ulid. Normalise to the
        // raw ulid column value before looking up — otherwise the where()
        // never matches anything and CORS silently denies every request.
        $ulid = self::getId($kid);

        return Cache::remember(
            self::originsCacheKey($ulid),
            self::ORIGINS_CACHE_TTL,
            fn () => self::query()
                ->where('ulid', $ulid)
                ->value('allowed_origins')
                ?->filter()
                ->values()
                ->all() ?? [],
        );
    }

    private static function originsCacheKey(string $ulid): string
    {
        return "clients:{$ulid}:allowed_origins";
    }

    protected static function booted(): void
    {
        $forget = function (Client $client) {
            if ($client->ulid) {
                Cache::forget(self::originsCacheKey($client->ulid));
            }
        };

        static::saved(function (Client $client) use ($forget) {
            if ($client->wasChanged('allowed_origins')) {
                $forget($client);
            }
        });

        static::deleted($forget);
    }

    public function createToken(string $name, array $abilities = ['*'], DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => $plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'hash' => hash('sha256', $plainTextToken),
        ]);

        return new NewAccessToken($token, $plainTextToken);
    }

    public function generateTokenString()
    {
        return base64_encode(random_bytes(24));
    }
}
