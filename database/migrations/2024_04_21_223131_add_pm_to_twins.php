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
        Schema::table('twins', function (Blueprint $table) {
            //            $table->enum('type', array_map(fn (TwinType $twin) => $twin->value, TwinType::cases()))->change();
            $table->string('type', 32)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('twins', function (Blueprint $table) {
            $table->string('type', 128)->change();
        });
    }
};
