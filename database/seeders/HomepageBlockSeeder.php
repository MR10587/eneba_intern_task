<?php

namespace Database\Seeders;

use App\Models\HomepageBlock;
use Illuminate\Database\Seeder;

/**
 * Seed backend-configured homepage blocks.
 *
 * WHY: all visible copy and product selection live in `config`, not in the SPA.
 * A disabled block is included so /home proves it ignores disabled rows.
 */
class HomepageBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'key' => 'home-banner',
                'title' => 'Featured game',
                'type' => 'banner',
                'config' => [
                    'headline' => 'Your next favourite, ready to play',
                    'subheadline' => 'Instant digital delivery from trusted sellers.',
                    'cta_label' => 'View deal',
                    'product_slug' => 'minecraft',
                ],
                'position' => 0,
                'is_enabled' => true,
            ],
            [
                'key' => 'best-sellers',
                'title' => 'Best sellers',
                'type' => 'best_sellers',
                'config' => ['limit' => 8],
                'position' => 1,
                'is_enabled' => true,
            ],
            [
                'key' => 'deals',
                'title' => 'Deals of the week',
                'type' => 'deals',
                'config' => ['limit' => 8],
                'position' => 2,
                'is_enabled' => true,
            ],
            [
                'key' => 'editorial-disabled',
                'title' => 'Editorial picks',
                'type' => 'editorial',
                'config' => ['limit' => 4],
                'position' => 3,
                'is_enabled' => false,
            ],
        ];

        foreach ($blocks as $block) {
            HomepageBlock::updateOrCreate(['key' => $block['key']], $block);
        }
    }
}
