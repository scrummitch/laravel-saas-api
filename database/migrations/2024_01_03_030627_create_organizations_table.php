<?php

use App\Models\Values\MembershipType;
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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->string('name')->nullable();
            //            $table->string('domain')->nullable();
            //            $table->string('timezone')->default('UTC');
            //            $table->string('document_locale')->default('en_US');
            $table->string('signup_token', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('organization_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('role', array_map(fn ($case) => $case->value, MembershipType::cases()))->default(MembershipType::member->value);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('organization_id');
            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('organizations');
    }
};
