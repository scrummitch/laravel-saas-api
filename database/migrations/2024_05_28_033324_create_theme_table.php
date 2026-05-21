<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('convert_themes', function (Blueprint $table) {
            $table->id();

            $table->ulid();
            $table->integer('organization_id');
            $table->string('border_radius', 4)->default('md');
            $table->string('page_bg_color', 7)->default('#007bff');
            $table->string('button_bg', 7)->default('#007bff');
            $table->string('button_text_color', 7)->default('#ffffff');
            $table->string('link_color', 7)->default('#007bff');
            $table->string('base_font_size', 2)->default('16');
            $table->string('base_text_color', 7)->default('#212529');
            $table->string('paragraph_line_height', 3)->default('1.5');
            $table->string('font_family', 50)->default('Arial, sans-serif');
            $table->string('vertical_spacing', 4)->default('md');
            $table->string('input_size', 4)->default('2');
            $table->string('input_variant', 16)->default('surface');
            $table->string('input_color', 7)->default('#f8f9fa');

            $table->timestamps();
        });

        // select all orgs that don't have a theme and create a default theme for them
        DB::table('mgmt_organizations')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('convert_themes')
                    ->whereColumn('mgmt_organizations.id', 'convert_themes.organization_id');
            })
            ->orderBy('id', 'desc')
            ->each(function ($org) {
                DB::table('convert_themes')->insert([
                    'ulid' => Str::ulid(),
                    'organization_id' => $org->id,
                    'border_radius' => 'm',
                    'page_bg_color' => '#ffffff',
                    'button_bg' => '#007bff',
                    'button_text_color' => '#ffffff',
                    'link_color' => '#007bff',
                    'base_font_size' => '16',
                    'base_text_color' => '#212529',
                    'paragraph_line_height' => '1.5',
                    'font_family' => 'Arial, sans-serif',
                    'vertical_spacing' => 'base',
                    'input_size' => '2',
                    'input_variant' => 'stripe',
                    'input_color' => '#f8f9fa',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_themes');
    }
};
