<?php

use App\Jobs\EnsureClientsHaveApiKeys;
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
        DB::table('personal_access_tokens')->truncate();

        if (Schema::hasColumn('personal_access_tokens', 'hash')) {
            $this->down();
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('hash', 64);
            $table->dropColumn('token');
            $table->text('token');
        });

        dispatch_sync(new EnsureClientsHaveApiKeys);

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unique('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('personal_access_tokens')->truncate();

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (Schema::hasIndex('personal_access_tokens', ['hash'], 'unique')) {
                $table->dropUnique(['hash']);
            }

            $table->dropColumn('hash');
            $table->dropColumn('token');
            $table->string('token', 64)->unique();
        });
    }
};
