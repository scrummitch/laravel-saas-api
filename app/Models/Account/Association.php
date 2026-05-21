<?php

namespace App\Models\Account;

use App\Models\Management\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The relationship between an Agent and a Customer
 *
 * @module Account
 *
 * @property Customer $customer
 * @property Agent $agent
 * @property Organization $organization
 * @property int $customer_id
 * @property int $agent_id
 * @property int $organization_id
 * @property string $key Identifier of the group (eg: team_id or organization_id)
 */
class Association extends Pivot
{
    protected $table = 'account_associations';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
