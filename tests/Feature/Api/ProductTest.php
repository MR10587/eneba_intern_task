<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /api/products/{slug}.
 *
 * WHY: the product page is the entry point for offer selection, so the
 * availability flag and the product_id + offer_id pair must be reliable.
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_returns_seller_offers_with_availability(): void
    {
        $product = Product::factory()->create([
            'name' => 'Minecraft',
            'platform' => 'Steam',
            'release_date' => '2011-11-18',
            'compare_at_price_cents' => 2999,
        ]);

        $available = Offer::factory()->for($product)->create([
            'price_cents' => 1999,
            'region' => 'Europe',
        ]);
        DigitalKey::factory()->for($available)->create();

        $soldOut = Offer::factory()->for($product)->create([
            'price_cents' => 1799,
            'region' => 'Global',
        ]);
        DigitalKey::factory()->for($soldOut)->sold()->create();

        $response = $this->getJson('/api/products/'.$product->slug);

        $response->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.name', 'Minecraft')
            ->assertJsonPath('data.release_date', '2011-11-18')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('meta.available_offer_count', 1)
            // WHY: detail must agree with the list — the cheaper sold-out
            // offer (1799) must not become the product's price. This also
            // pins the previously missing min_price_cents on this endpoint.
            ->assertJsonPath('data.min_price_cents', 1999)
            ->assertJsonPath('data.discount_percent', 33)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.available_offer_count', 1);

        $this->assertCount(2, $response->json('data.offers'));

        // Offers are ordered by price, so the cheaper sold-out one comes first.
        $response->assertJsonPath('data.offers.0.id', $soldOut->id)
            ->assertJsonPath('data.offers.0.available', false)
            ->assertJsonPath('data.offers.0.price_cents', 1799)
            ->assertJsonPath('data.offers.1.id', $available->id)
            ->assertJsonPath('data.offers.1.available', true)
            ->assertJsonPath('data.offers.1.price_cents', 1999)
            ->assertJsonPath('data.offers.1.currency', 'EUR');

        $this->assertNotEmpty($response->json('data.offers.1.seller.name'));
    }

    public function test_inactive_offers_are_hidden_from_product_detail(): void
    {
        $product = Product::factory()->create();
        $visible = Offer::factory()->for($product)->create(['price_cents' => 1000]);
        DigitalKey::factory()->for($visible)->create();

        $inactive = Offer::factory()->for($product)->inactive()->create(['price_cents' => 500]);
        DigitalKey::factory()->for($inactive)->create();

        $response = $this->getJson('/api/products/'.$product->slug);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.offers'));
        $response->assertJsonPath('data.offers.0.id', $visible->id);
    }

    public function test_sold_out_product_detail_has_null_price_and_no_discount(): void
    {
        $product = Product::factory()->create(['compare_at_price_cents' => 2999]);
        $offer = Offer::factory()->for($product)->create(['price_cents' => 1200]);
        DigitalKey::factory()->for($offer)->sold()->create();

        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.min_price_cents', null)
            ->assertJsonPath('data.discount_percent', null)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.available_offer_count', 0)
            ->assertJsonPath('data.offers.0.available', false);
    }

    public function test_inactive_product_returns_product_not_found(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson('/api/products/'.$product->slug)
            ->assertStatus(404)
            ->assertJsonPath('code', 'PRODUCT_NOT_FOUND')
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_missing_product_returns_product_not_found(): void
    {
        $this->getJson('/api/products/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('code', 'PRODUCT_NOT_FOUND');
    }
}
