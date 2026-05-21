<?php

namespace App\Models\Account;

use App\Database\Model;
use App\Database\Traits\HasLookupKey;
use App\Models\Client;
use App\Models\Management\Organization;
use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * An Agent is an end-user which is allowed to make requests on behalf of a Customer.
 *
 * @module Account
 *
 * @property Organization $organization
 * @property int $organization_id
 * @property int $customer_id
 * @property bool $is_sandbox_user
 * @property string $key The Agents unique identifier (external)
 * @property string $name The name of the Agent.
 * @property string $email Email Address
 */
class Agent extends Model
{
    use HasFactory,
        HasLookupKey;

    protected $table = 'account_agents';

    protected $fillable = [
        'organization_id',
        'lookup_key',
        'name',
        'email',
        'is_sandbox_user',
    ];

    protected $casts = [
        'is_sandbox_user' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function associations(): HasMany
    {
        return $this->hasMany(Association::class);
    }

    public function token(Client $client, array $additionalClaims = []): string
    {
        $claims = array_merge([
            'sub' => null,
        ], $additionalClaims);

        return JWT::encode($claims, $client->getSecretStr(), 'HS256', $client->getRouteKey());
    }

    public function associateWithCustomer(Customer $customer)
    {
        DB::table((new Association)->getTable())
            ->insert([
                'key' => null,
                'agent_id' => $this->id,
                'customer_id' => $customer->id,
                'organization_id' => $customer->organization_id,
            ]);

    }
}
