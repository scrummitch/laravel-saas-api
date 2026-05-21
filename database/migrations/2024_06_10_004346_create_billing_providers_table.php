<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_providers', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_key', 64);
            $table->string('type', 32);
            $table->foreignId('organization_id');
            $table->string('name', 64)->nullable();
            $table->text('secret')->nullable();
            $table->json('config')->nullable();
            $table->enum('environment', ['live', 'test']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->bigInteger('test_billing_provider_id')->nullable()->after('invite_code');
            $table->bigInteger('live_billing_provider_id')->nullable()->after('test_billing_provider_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('billing_provider_id')->nullable()->constrained('billing_providers');
        });
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->dropColumn('connection_id');
        });

        Schema::table('account_customers', function (Blueprint $table) {
            $table->foreignId('billing_provider_id')->nullable()->constrained('billing_providers');
        });
        Schema::table('account_customers', function (Blueprint $table) {
            $table->string('reference_id')->nullable()->change();
        });

        DB::table('connections')
            ->orderBy('id')
            ->each(function ($data) {
                DB::table('billing_providers')
                    ->insertOrIgnore([
                        'lookup_key' => $data->provider_id,
                        'type' => $data->provider_name,
                        'organization_id' => $data->organization_id,
                        'name' => $data->name,
                        'secret' => $data->secret,
                        'config' => $data->config,
                        'environment' => $data->is_live ? 'live' : 'test',
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes to `mgmt_organizations`
        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->dropColumn('test_billing_provider_id');
            $table->dropColumn('live_billing_provider_id');
        });

        // Revert changes to `clients`
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['billing_provider_id']);
            $table->dropColumn('billing_provider_id');
        });

        // Revert changes to `convert_paywalls`
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->bigInteger('connection_id')->nullable();
        });

        // Revert changes to `account_customers`
        Schema::table('account_customers', function (Blueprint $table) {
            $table->dropForeign(['billing_provider_id']);
            $table->dropColumn('billing_provider_id');
            $table->string('reference_id')->nullable(false)->change(); // Reverting to non-nullable if necessary
        });

        // Drop the `billing_providers` table
        Schema::dropIfExists('billing_providers');
    }
};
