<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->json('template')->nullable()->change();
        });

        DB::table('convert_paywalls')
            ->get()
            ->each(function ($paywall) {
                $stages = json_decode($paywall->stages, true);

                if (collect($stages)->pluck('template')->filter()->isEmpty()) {
                    return;
                }

                $newStages = collect($stages)
                    ->filter(fn ($stage) => array_key_exists('template', $stage))
                    ->map(function ($stage) {
                        $newStage = $stage;
                        $newStage['layout'] = ['sm' => 'split-checkout@v1'];

                        $slots = $stage['template']['slots'];
                        $contentBlocks = Arr::get($slots, 'content.blocks', []);

                        foreach ($contentBlocks as $index => $block) {
                            if (Arr::get($block, 'type') === 'dl') {
                                $contentBlocks[$index] = $this->remapDlBlock($block);
                            }

                            $hasIcon = collect(Arr::get($block, 'text', []))
                                ->where('object', 'icon')
                                ->isNotEmpty();

                            if ($hasIcon) {
                                $contentBlocks[$index] = $this->remapBlockWithIcon($block);
                            }

                        }
                        $slots['content']['blocks'] = $contentBlocks;

                        $newStage['view'] = $slots;
                        unset($newStage['template']);

                        return $newStage;
                    })
                    ->toArray();

                DB::table('convert_paywalls')
                    ->where('id', $paywall->id)
                    ->update([
                        'stages' => json_encode($newStages),
                    ]);
            });
    }

    private function remapBlockWithIcon($block): array
    {
        $texts = $block['text'];

        foreach ($texts as $index => $span) {
            if (Arr::get($span, 'object') !== 'icon') {
                continue;
            }

            $newSpan = $span;
            $newSpan['props']['icon'] = $span['props']['content'];
            unset($newSpan['props']['content']);
            $texts[$index] = $newSpan;
        }

        $block['text'] = $texts;

        return $block;
    }

    private function remapDlBlock($block): array
    {
        return [
            'type' => 'dl',
            'object' => 'block',
            'children' => collect($block['children'])
                ->map(function ($child) {
                    return [
                        'type' => 'dl-group',
                        'object' => 'block',
                        'children' => [
                            [
                                'type' => 'dt',
                                'object' => 'icon',
                                'props' => [
                                    'icon' => 'check',
                                    'size' => 6,
                                    'variant' => 'solid',
                                ],
                                'style' => [
                                    'borderRadius' => '60px',
                                    'backgroundColor' => 'black',
                                    'color' => 'white',
                                ],
                            ],
                            [
                                'type' => 'dd',
                                'children' => [
                                    [
                                        'type' => 'p',
                                        'object' => 'block',
                                        'text' => $child['text'],
                                    ],
                                ],
                            ],
                        ],
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
