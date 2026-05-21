<?php

use App\Models\Convert\CheckoutState;
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
        Schema::create('convert_checkouts', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id');
            $table->integer('agent_id');
            $table->morphs('initiator');
            $table->integer('customer_id')->nullable();
            $table->integer('offering_id');

            $table->string('trigger');
            $table->enum('type', ['embed', 'modal', 'redirect', 'external']);
            $table->enum('current_state', array_map(fn (CheckoutState $state) => $state->value, CheckoutState::cases()));
            $table->string('token', 32);
            $table->json('line_items');
            $table->json('config');

            $table->timestamp('entered_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->boolean('has_entered')->default(false);
            $table->boolean('has_started')->default(false);
            $table->boolean('has_completed')->default(false);

            $table->timestamps();
        });

        Schema::create('convert_checkout_mutations', function (Blueprint $table) {
            $table->id();
            $table->integer('checkout_id');
            $table->string('type');
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_checkout_mutations');
        Schema::dropIfExists('convert_checkouts');
    }
};
