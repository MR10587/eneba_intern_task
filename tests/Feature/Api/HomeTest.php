<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\HomepageBlock;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /api/home.
 *
 * WHY: these tests pin the block envelope, enabled/ordering rules, and the three
 * MVP resolvers. They also prove that bad configuration degrades gracefully.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a product with one purchasable offer at a known price.
     *
     * @param  array<string, mixed>  $productAttributes
     */
    private function purchasableProduct(array $productAttributes = [], int $priceCents = 1999): Product
    {
        $product = Product::factory()->create($productAttributes);
        $offer = Offer::factory()->for($product)->create(['price_cents' => $priceCents]);
        DigitalKey::factory()->for($offer)->create();

        return $product;
    }

    public function test_empty_home_returns_empty_blocks(): void
    {
        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.block_count', 0);
    }

    public function test_disabled_blocks_are_excluded(): void
    {
        $enabled = HomepageBlock::factory()->ofType('best_sellers')->create([
            'key' => 'enabled-block',
            'position' => 0,
        ]);
        HomepageBlock::factory()->disabled()->ofType('best_sellers')->create([
            'key' => 'disabled-block',
            'position' => 1,
        ]);

        $response = $this->getJson('/api/home');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains($enabled->key));
        $this->assertFalse($keys->contains('disabled-block'));
        $response->assertJsonPath('meta.block_count', 1);
    }

    public function test_blocks_are_ordered_by_position_then_id(): void
    {
        HomepageBlock::factory()->ofType('best_sellers')->create(['key' => 'third', 'position' => 2]);
        HomepageBlock::factory()->ofType('best_sellers')->create(['key' => 'first', 'position' => 1]);
        HomepageBlock::factory()->ofType('best_sellers')->create(['key' => 'second', 'position' => 1]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'first')
            ->assertJsonPath('data.1.key', 'second')
            ->assertJsonPath('data.2.key', 'third');
    }

    public function test_banner_resolves_active_product_and_config_copy(): void
    {
        $product = $this->purchasableProduct([
            'name' => 'Home Banner Game',
            'slug' => 'home-banner-game',
        ], 1999);

        HomepageBlock::factory()->ofType('banner')->create([
            'key' => 'hero',
            'title' => 'Featured game',
            'position' => 0,
            'config' => [
                'headline' => 'Featured this week',
                'subheadline' => 'A great price',
                'cta_label' => 'Buy now',
                'cta_href' => '/products/home-banner-game',
                'product_slug' => $product->slug,
            ],
        ]);

        $response = $this->getJson('/api/home');

        $response->assertOk()
            ->assertJsonPath('data.0.key', 'hero')
            ->assertJsonPath('data.0.title', 'Featured game')
            ->assertJsonPath('data.0.type', 'banner')
            ->assertJsonPath('data.0.position', 0)
            ->assertJsonPath('data.0.data.headline', 'Featured this week')
            ->assertJsonPath('data.0.data.subheadline', 'A great price')
            ->assertJsonPath('data.0.data.cta_label', 'Buy now')
            ->assertJsonPath('data.0.data.cta_href', '/products/home-banner-game')
            ->assertJsonPath('data.0.data.product.slug', $product->slug)
            ->assertJsonPath('data.0.data.product.min_price_cents', 1999)
            ->assertJsonPath('data.0.data.product.currency', 'EUR');

        // Banners carry catalog summaries, not the product detail payload.
        $this->assertArrayNotHasKey('offers', $response->json('data.0.data.product'));
    }

    public function test_banner_with_missing_or_inactive_product_degrades_to_null(): void
    {
        $inactive = $this->purchasableProduct([
            'name' => 'Inactive Banner Game',
            'slug' => 'inactive-banner-game',
            'is_active' => false,
        ]);

        HomepageBlock::factory()->ofType('banner')->create([
            'key' => 'missing-product-banner',
            'position' => 0,
            'config' => [
                'headline' => 'Still renders',
                'product_slug' => 'does-not-exist',
            ],
        ]);
        HomepageBlock::factory()->ofType('banner')->create([
            'key' => 'inactive-product-banner',
            'position' => 1,
            'config' => [
                'headline' => 'Also renders',
                'product_slug' => $inactive->slug,
            ],
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.0.data.headline', 'Still renders')
            ->assertJsonPath('data.0.data.product', null)
            ->assertJsonPath('data.1.data.headline', 'Also renders')
            ->assertJsonPath('data.1.data.product', null);
    }

    public function test_best_sellers_honors_limit_and_popularity_order(): void
    {
        $this->purchasableProduct(['name' => 'Low Pop', 'popularity' => 10], 1000);
        $high = $this->purchasableProduct(['name' => 'High Pop', 'popularity' => 900], 1000);
        $mid = $this->purchasableProduct(['name' => 'Mid Pop', 'popularity' => 500], 1000);

        HomepageBlock::factory()->ofType('best_sellers')->create([
            'key' => 'best-sellers',
            'position' => 0,
            'config' => ['limit' => 2],
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(2, 'data.0.data')
            ->assertJsonPath('data.0.data.0.slug', $high->slug)
            ->assertJsonPath('data.0.data.1.slug', $mid->slug)
            ->assertJsonPath('data.0.data.0.min_price_cents', 1000)
            ->assertJsonPath('data.0.data.0.available', true);
    }

    public function test_deals_only_include_purchasable_discounted_products(): void
    {
        $deal = $this->purchasableProduct([
            'name' => 'Real Deal',
            'compare_at_price_cents' => 3000,
        ], 1000);

        $this->purchasableProduct(['name' => 'No Compare Price'], 1000);

        $soldOut = Product::factory()->create([
            'name' => 'Sold Out Deal',
            'compare_at_price_cents' => 3000,
        ]);
        $soldOutOffer = Offer::factory()->for($soldOut)->create(['price_cents' => 100]);
        DigitalKey::factory()->for($soldOutOffer)->sold()->create();

        $this->purchasableProduct([
            'name' => 'Inactive Deal',
            'is_active' => false,
            'compare_at_price_cents' => 3000,
        ], 100);

        HomepageBlock::factory()->ofType('deals')->create([
            'key' => 'deals',
            'position' => 0,
            'config' => ['limit' => 10],
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.data')
            ->assertJsonPath('data.0.data.0.slug', $deal->slug)
            ->assertJsonPath('data.0.data.0.min_price_cents', 1000)
            ->assertJsonPath('data.0.data.0.discount_percent', 67);
    }

    public function test_unknown_block_type_returns_empty_data_without_failing(): void
    {
        HomepageBlock::factory()->ofType('mystery')->create([
            'key' => 'mystery',
            'position' => 0,
        ]);
        HomepageBlock::factory()->ofType('best_sellers')->create([
            'key' => 'known',
            'position' => 1,
        ]);

        $response = $this->getJson('/api/home');

        $response->assertOk()
            ->assertJsonPath('data.0.key', 'mystery')
            ->assertJsonPath('data.0.type', 'mystery')
            ->assertJsonPath('data.1.key', 'known')
            ->assertJsonPath('meta.block_count', 2);

        $this->assertSame([], $response->json('data.0.data'));
    }
}
