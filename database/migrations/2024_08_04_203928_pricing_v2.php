<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // if feature_id doesnt exist on catalog_inclusions, lets add it
        if (! Schema::hasColumn('catalog_inclusions', 'feature_id')) {
            Schema::table('catalog_inclusions', function (Blueprint $table) {
                $table->bigInteger('feature_id')->nullable()->after('product_feature_id');
            });
        } else {
            Schema::table('catalog_inclusions', function (Blueprint $table) {
                $table->bigInteger('feature_id')->nullable()->change();
            });
        }

        // if pricing_plans table doesnt exist
        if (! Schema::hasTable('pricing_plans')) {
            Schema::table('catalog_plans', function (Blueprint $table) {
                $table->bigInteger('package_id')->nullable()->after('organization_id');
            });

            Schema::table('catalog_plans', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });

            Schema::rename('catalog_plans', 'pricing_plans');

            Schema::table('pricing_schemes', function (Blueprint $table) {
                $table->jsonb('active_currencies')->nullable();
                $table->jsonb('active_intervals')->nullable();
            });

            Schema::table('billing_charges', function (Blueprint $table) {
                $table->string('description')->nullable()->after('name');
                $table->string('invoicing_interval')->nullable()->after('name');
            });

            Schema::table('catalog_inclusions', function (Blueprint $table) {
                $table->unique(['plan_id', 'charge_id']);
            });

            Schema::table('convert_checkouts', function (Blueprint $table) {
                $table->char('currency', 3)->nullable();
            });

            Schema::table('mgmt_organizations', function (Blueprint $table) {
                $table->char('default_currency')->nullable();
            });
        }

        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropUnique('product_interval_currency');
        });

        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn('interval');
        });

        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('account_customers', function (Blueprint $table) {
            $table->timestamp('reference_created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        // Revert changes to `catalog_inclusions`
        if (Schema::hasColumn('catalog_inclusions', 'feature_id')) {
            Schema::table('catalog_inclusions', function (Blueprint $table) {
                $table->dropColumn('feature_id');
            });
        }

        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->dropUnique(['plan_id', 'charge_id']);
        });

        // Revert renaming `catalog_plans` to `pricing_plans`
        if (Schema::hasTable('pricing_plans')) {
            Schema::rename('pricing_plans', 'catalog_plans');

            Schema::table('catalog_plans', function (Blueprint $table) {
                $table->dropColumn('package_id');
                $table->bigInteger('product_id')->nullable()->after('organization_id');
            });
        }

        // Revert changes to `pricing_schemes`
        Schema::table('pricing_schemes', function (Blueprint $table) {
            $table->dropColumn('active_currencies');
            $table->dropColumn('active_intervals');
        });

        // Revert changes to `billing_charges`
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropColumn('invoicing_interval');
        });

        // Revert changes to `convert_checkouts`
        if (Schema::hasColumn('convert_checkouts', 'currency')) {
            Schema::table('convert_checkouts', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }

        // Revert changes to `mgmt_organizations`
        if (Schema::hasColumn('mgmt_organizations', 'default_currency')) {
            Schema::table('mgmt_organizations', function (Blueprint $table) {
                $table->dropColumn('default_currency');
            });
        }

        // Revert changes to `pricing_packages`
        if (!Schema::hasColumn('pricing_packages', 'interval')) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->string('interval')->nullable()->after('product_id');
            });
        }

        if (!Schema::hasColumn('pricing_packages', 'currency')) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->char('currency', 3)->nullable()->after('interval');
            });
        }

        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->unique(['product_id', 'interval', 'currency'], 'product_interval_currency');
        });

        // Revert changes to `account_customers`
        if (Schema::hasColumn('account_customers', 'reference_created_at')) {
            Schema::table('account_customers', function (Blueprint $table) {
                $table->dropIndex(['reference_created_at']);
                $table->dropColumn('reference_created_at');
            });
        }
    }
};
