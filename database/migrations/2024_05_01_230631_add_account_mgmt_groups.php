<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('agent_associations', 'account_associations');
        Schema::rename('agents', 'account_agents');
        Schema::rename('customers', 'account_customers');
        Schema::rename('operations', 'mgmt_operations');
        Schema::rename('organizations', 'mgmt_organizations');
        Schema::rename('organization_users', 'mgmt_memberships');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('account_associations', 'agent_associations');
        Schema::rename('account_agents', 'agents');
        Schema::rename('account_customers', 'customers');
        Schema::rename('mgmt_operations', 'operations');
        Schema::rename('mgmt_organizations', 'organizations');
        Schema::rename('mgmt_memberships', 'organization_users');
    }
};
