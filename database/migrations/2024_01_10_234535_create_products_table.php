<?php

use App\Models\Values\ProductStatus;
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('ancestor_id')->nullable();
            $table->bigInteger('successor_id')->nullable();
            $table->enum('status', array_map(fn (ProductStatus $case) => $case->value, ProductStatus::cases()))->default(ProductStatus::draft->value);
            $table->string('name', 128);
            $table->string('display_name', 128)->nullable();
            $table->integer('version_number', false, true)->default(1);
            $table->string('version_name', 32);
            $table->string('key', 64);
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'key', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
