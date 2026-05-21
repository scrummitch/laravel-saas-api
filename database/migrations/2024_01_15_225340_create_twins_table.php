<?php

use App\Models\Values\TwinType;
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
        Schema::create('twins', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('connection_id');
            $table->enum('type', array_map(fn (TwinType $twin) => $twin->value, TwinType::cases()));
            $table->string('reference_id', 128)->index();
            $table->jsonb('data');
            $table->timestamps(6);
            $table->timestamp('reference_created_at')->nullable();
            $table->softDeletes();
            $table->unique(['connection_id', 'reference_id', 'type']);
        });

        Schema::create('twin_links', function (Blueprint $table) {
            $table->foreignId('twin_id')->constrained('twins');
            $table->morphs('linkable');
            $table->unique(['twin_id', 'linkable_type', 'linkable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twin_links');
        Schema::dropIfExists('twins');
    }
};
