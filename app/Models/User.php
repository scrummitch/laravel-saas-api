<?php

namespace App\Models;

use App\Database\Traits\HasNiceUlids;
use App\Models\Management\Membership;
use App\Models\Management\Organization;
use DateTimeInterface;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * @property int $id
 * @property string $ulid
 * @property string $email
 * @property string $password
 * @property string $first_name
 * @property string $last_name
 * @property string $timezone
 * @property string $remember_token
 * @property string $email_verified_at
 * @property string $created_at
 * @property string $updated_at
 * @property Collection<Membership> $memberships
 * @property Collection<Organization> $organizations
 * @property Organization $currentOrganization
 * @property int $last_organization_id
 */
class User extends Authenticatable
{
    use HasApiTokens,
        HasFactory,
        HasNiceUlids,
        MustVerifyEmail,
        Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function getOrganizationIdAttribute(): int
    {
        if (! is_null($this->last_organization_id)) {
            return $this->last_organization_id;
        }

        $firstMembership = optional($this->memberships->first())->organization_id;

        if (! is_null($firstMembership)) {
            return $firstMembership;
        }

        // create a new org
        $organization = new Organization;
        $organization->name = $this->email;
        $organization->save();
        $organization->users()->attach($this, ['role' => 'owner']);

        $this->last_organization_id = $organization->id;
        $this->save();

        return $organization->id;
    }

    public function getCurrentOrganizationAttribute()
    {
        return Organization::query()->find($this->organization_id);
    }

    public function organizations(): BelongsToMany
    {
        return $this
            ->belongsToMany(Organization::class, 'mgmt_memberships')
            ->withPivot('role')
            ->wherePivotNull('revoked_at');
    }

    public function createToken(string $name, array $abilities = ['*'], DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'hash' => hash('sha256', $plainTextToken),
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
