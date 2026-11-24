<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for GET /api/search.
 *
 * WHY: search is a separate read model from the catalog, but it must not
 * diverge on visibility or pricing. These tests pin both contracts plus the
 * documented PostgreSQL/SQLite driver behaviour.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a product with one purchasable offer at a known price.
     *
     * @param  array<string, mixed>  $productAttributes
     * @param  array<string, mixed>  $offerAttributes
     */
    private function purchasableProduct(
        array $productAttributes = [],
        int $priceCents = 1999,
        array $offerAttributes = [],
    ): Product {
        $product = Product::factory()->create($productAttributes);
        $offer = Offer::factory()->for($product)->create(array_merge([
            'price_cents' => $priceCents,
        ], $offerAttributes));
        DigitalKey::factory()->for($offer)->create();

        return $product;
    }

    public function test_partial_match_returns_catalog_price_fields_and_result_count(): void
    {
        $match = $this->purchasableProduct(['name' => 'Minecraft', 'popularity' => 500], 1999);
        $this->purchasableProduct(['name' => 'Fortnite'], 2500);

        $response = $this->getJson('/api/search?q=minec');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $match->slug)
            ->assertJsonPath('data.0.name', 'Minecraft')
            ->assertJsonPath('data.0.min_price_cents', 1999)
            ->assertJsonPath('data.0.available_offer_count', 1)
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.currency', 'EUR')
            ->assertJsonPath('meta.query', 'minec')
            ->assertJsonPath('meta.result_count', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        // Search rows are catalog-style summaries, never product detail rows.
        $this->assertArrayNotHasKey('offers', $response->json('data.0'));
    }

    public function test_search_never_returns_inactive_products(): void
    {
        $hidden = $this->purchasableProduct(['name' => 'Hidden Quest', 'is_active' => false]);
        $visible = $this->purchasableProduct(['name' => 'Hidden Quest II']);

        $response = $this->getJson('/api/search?q=hidden');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($visible->slug));
        $this->assertFalse($slugs->contains($hidden->slug));
        $response->assertJsonPath('meta.result_count', 1);
    }

    public function test_sold_out_offers_do_not_contribute_to_search_prices(): void
    {
        $product = $this->purchasableProduct(['name' => 'Priced Game'], 2500);

        $soldOut = Offer::factory()->for($product)->create(['price_cents' => 500]);
        DigitalKey::factory()->for($soldOut)->sold()->create();

        $inactive = Offer::factory()->for($product)->inactive()->create(['price_cents' => 100]);
        DigitalKey::factory()->for($inactive)->create();

        $response = $this->getJson('/api/search?q=priced');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('data.0.min_price_cents', 2500)
            ->assertJsonPath('data.0.available_offer_count', 1);
    }

    public function test_no_matches_returns_empty_data_with_zero_count(): void
    {
        $this->purchasableProduct(['name' => 'Minecraft']);

        $response = $this->getJson('/api/search?q=zzzznotfound');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.result_count', 0)
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('meta.pagination.last_page', 1);
    }

    public function test_pagination_metadata_is_returned(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->purchasableProduct([
                'name' => 'Search Game '.$i,
                'popularity' => $i,
            ]);
        }

        $response = $this->getJson('/api/search?q=search%20game&page=2&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.from', 3)
            ->assertJsonPath('meta.pagination.to', 4);
    }

    public function test_typo_behaviour_matches_the_active_database_driver(): void
    {
        $this->purchasableProduct(['name' => 'Minecraft']);

        $response = $this->getJson('/api/search?q=mincraft');

        $response->assertOk();

        if (DB::connection()->getDriverName() === 'pgsql') {
            // pg_trgm similarity (~0.58) clears the 0.3 threshold.
            $response->assertJsonPath('meta.result_count', 1);
        } else {
            // SQLite has no similarity() function, so only LIKE is available.
            $response->assertJsonPath('meta.result_count', 0);
        }
    }

    public function test_invalid_parameters_use_validation_error_envelope(): void
    {
        $this->getJson('/api/search')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'The given parameters were invalid.')
            ->assertJsonStructure(['message', 'code', 'errors' => ['q']]);

        $this->getJson('/api/search?q=a&per_page=49')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'code', 'errors' => ['q', 'per_page']]);
    }
}
