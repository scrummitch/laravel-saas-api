<?php

use App\Models\Values\PlanType;
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
        Schema::create('catalog_plans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');

            $table->enum('type', array_map(fn ($t) => $t->value, PlanType::cases()))->default(PlanType::standard->value);
            $table->bigInteger('base_charge_id')->nullable();

            $table->string('display_name')->nullable();
            $table->string('description', 256)->nullable();
            $table->string('name');

            $table->char('period', 16);
            $table->char('currency', 4);

            $table->integer('trial_length')->nullable();
            $table->string('trial_credit')->nullable();
            $table->string('trial_unit')->nullable();

            $table->bigInteger('minimum_charge')->nullable();
            $table->bigInteger('minimum_charge_frequency')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('catalog_plan_inclusions', function (Blueprint $table) {
            $table->bigInteger('plan_id');
            $table->bigInteger('feature_id')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->bigInteger('charge_id')->nullable();
            $table->float('limit')->nullable();
            $table->string('limit_unit', 64)->nullable();
            $table->string('note', 256)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_plan_inclusions');
        Schema::dropIfExists('catalog_plans');
    }
};
