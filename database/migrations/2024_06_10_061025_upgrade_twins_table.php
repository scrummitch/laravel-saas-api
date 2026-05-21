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
        Schema::dropIfExists('twin_links');

        Schema::table('twins', function (Blueprint $table) {
            $table->string('type', 128)->change();
        });

        Schema::table('twins', function (Blueprint $table) {
            $table->renameColumn('connection_id', 'connector_id');
        });

        Schema::table('twins', function (Blueprint $table) {
            $table->string('connector_type')->default('billing_provider')->after('organization_id');
            $table->index(['connector_type', 'connector_id']);
            $table->nullableMorphs('linkable');
        });

        $map = [
            'product' => \Stripe\Product::class,
            'price' => \Stripe\Price::class,
            'customer' => \Stripe\Customer::class,
            'subscription' => \Stripe\Subscription::class,
            'invoice' => \Stripe\Invoice::class,
            'payment_intent' => \Stripe\PaymentIntent::class,
            'payment_method' => \Stripe\PaymentMethod::class,
            'setup_intent' => \Stripe\SetupIntent::class,
        ];

        foreach ($map as $key => $className) {
            DB::table('twins')
                ->where('type', $key)
                ->update(['type' => $className]);
        }

        Schema::table('twins', function (Blueprint $table) {
            $table->unique(['connector_type', 'connector_id', 'reference_id', 'type'], 'connector_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
