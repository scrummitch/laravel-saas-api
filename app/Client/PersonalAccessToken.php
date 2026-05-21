<?php

namespace App\Client;

use App\Database\Model;
use App\Models\Client;
use Carbon\Carbon;
use Laravel\Sanctum\Contracts\HasAbilities;
use Symfony\Component\Uid\BinaryUtil;
use Symfony\Component\Uid\Ulid;

/**
 * @property string $name
 * @property string $token
 * @property string $abilities
 * @property string $hash
 * @property Carbon $expires_at
 */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'token' => 'encrypted',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'hash',
        'expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Get the tokenable model that the access token belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function tokenable()
    {
        return $this->morphTo('tokenable');
    }

    public function getApiKey(): string
    {
        $ulidBytes = (new Ulid($this->tokenable->ulid))->toBinary();
        $apiKeyBytes = base64_decode($this->token);

        $combinedBytes = $ulidBytes . $apiKeyBytes;

        return implode('_', [
            'client',
            $this->tokenable->environment,
            BinaryUtil::toBase($combinedBytes, BinaryUtil::BASE58),
        ]);
    }

    /**
     * Find the token instance matching the given token.
     *
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        if (strpos($token, 'client_') !== 0) {
            return null;
        }

        [$prefix, $env, $encodedPart] = explode('_', $token);

        $decodedBytes = BinaryUtil::fromBase($encodedPart, BinaryUtil::BASE58);

        $apiKey = base64_encode(substr($decodedBytes, 16));

        return static::where('hash', hash('sha256', $apiKey))->first();
    }

    /**
     * Determine if the token has a given ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public function can($ability)
    {
        return in_array('*', $this->abilities) ||
            array_key_exists($ability, array_flip($this->abilities));
    }

    /**
     * Determine if the token is missing a given ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public function cant($ability)
    {
        return ! $this->can($ability);
    }
}
