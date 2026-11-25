<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /api/products and /api/products/filters.
 *
 * WHY: these tests are the executable version of the catalog contract —
 * they pin the response envelope, EUR pricing, and filter semantics.
 */
class CatalogTest extends TestCase
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

    public function test_filters_endpoint_returns_backend_derived_options_in_eur(): void
    {
        $this->purchasableProduct(
            ['platform' => 'Steam', 'name' => 'Alpha Game'],
            1000,
            ['region' => 'Europe'],
        );
        $this->purchasableProduct(
            ['platform' => 'Epic Games', 'name' => 'Beta Game'],
            2500,
            ['region' => 'Global'],
        );

        // A sold-out offer must not create a filter option.
        $soldOut = Product::factory()->create(['platform' => 'Xbox']);
        $soldOutOffer = Offer::factory()->for($soldOut)->create(['region' => 'US']);
        DigitalKey::factory()->for($soldOutOffer)->sold()->create();

        $response = $this->getJson('/api/products/filters');

        $response->assertOk()->assertJsonPath('data.currency', 'EUR');
        $this->assertEqualsCanonicalizing(
            config('catalog.categories'),
            $response->json('data.categories'),
        );
        $this->assertContains('Steam', $response->json('data.platforms'));
        $this->assertContains('Epic Games', $response->json('data.platforms'));
        $this->assertNotContains('Xbox', $response->json('data.platforms'));
        $this->assertEqualsCanonicalizing(['Europe', 'Global'], $response->json('data.regions'));
        $this->assertSame(1000, $response->json('data.price_range.min_cents'));
        $this->assertSame(2500, $response->json('data.price_range.max_cents'));
    }

    public function test_stored_category_filters_use_backend_pagination_and_metadata(): void
    {
        $giftCard = $this->purchasableProduct(
            ['name' => 'Steam Gift Card', 'category' => 'Gift Cards', 'platform' => 'Steam'],
            2000,
            ['region' => 'Europe'],
        );
        $this->purchasableProduct(
            ['name' => 'Another Game', 'category' => 'Games', 'platform' => 'Steam'],
            2500,
            ['region' => 'Europe'],
        );

        $response = $this->getJson('/api/products?category=Gift%20Cards&platform=Steam&region=Europe&max_price=2000');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $giftCard->slug)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.applied_filters.category', 'Gift Cards')
            ->assertJsonPath('meta.applied_filters.max_price_cents', 2000);
    }

    public function test_best_deals_is_a_backend_derived_filter(): void
    {
        $deal = $this->purchasableProduct(
            [
                'name' => 'Discounted Game',
                'category' => 'Games',
                'platform' => 'Steam',
                'compare_at_price_cents' => 5000,
            ],
            3000,
            ['region' => 'Europe'],
        );
        $this->purchasableProduct(
            ['name' => 'Full Price Game', 'category' => 'Games', 'platform' => 'Xbox'],
            3000,
            ['region' => 'US'],
        );

        $this->getJson('/api/products?category=Best%20Deals&platform=Steam&region=Europe&max_price=3000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $deal->slug)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.applied_filters.category', 'Best Deals');
    }

    public function test_unknown_category_uses_validation_error_envelope(): void
    {
        $this->getJson('/api/products?category=Consoles')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['category']]);
    }

    public function test_only_active_products_are_listed(): void
    {
        $visible = $this->purchasableProduct(['name' => 'Visible Game']);
        $hidden = $this->purchasableProduct(['name' => 'Hidden Game', 'is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($visible->slug));
        $this->assertFalse($slugs->contains($hidden->slug));
    }

    public function test_min_price_ignores_sold_out_and_inactive_offers(): void
    {
        $product = $this->purchasableProduct([], 2000);

        $soldOutOffer = Offer::factory()->for($product)->create(['price_cents' => 1000]);
        DigitalKey::factory()->for($soldOutOffer)->sold()->create();

        $inactiveOffer = Offer::factory()->for($product)->inactive()->create(['price_cents' => 500]);
        DigitalKey::factory()->for($inactiveOffer)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $this->assertSame(2000, $response->json('data.0.min_price_cents'));
        $this->assertSame(1, $response->json('data.0.available_offer_count'));
        $this->assertTrue($response->json('data.0.available'));
        $this->assertSame('EUR', $response->json('data.0.currency'));
    }

    public function test_unavailable_products_are_hidden_from_the_catalog(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $offer = Offer::factory()->for($product)->create(['price_cents' => 1200]);
        DigitalKey::factory()->for($offer)->sold()->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);

        // The product itself remains reachable by its detail URL.
        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_price_filters_compare_against_computed_min_price(): void
    {
        $cheap = $this->purchasableProduct(['name' => 'Cheap'], 1000);
        $expensive = $this->purchasableProduct(['name' => 'Expensive'], 5000);
        $unavailable = Product::factory()->create(['name' => 'Unavailable']);
        $offer = Offer::factory()->for($unavailable)->create(['price_cents' => 200]);
        DigitalKey::factory()->for($offer)->sold()->create();

        $response = $this->getJson('/api/products?min_price=900&max_price=1500');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($cheap->slug));
        $this->assertFalse($slugs->contains($expensive->slug));
        $this->assertFalse($slugs->contains($unavailable->slug));
    }

    public function test_platform_and_region_filters_are_case_insensitive(): void
    {
        $match = $this->purchasableProduct(
            ['platform' => 'Steam'],
            1999,
            ['region' => 'Europe'],
        );
        $other = $this->purchasableProduct(
            ['platform' => 'Xbox'],
            1999,
            ['region' => 'Global'],
        );

        $response = $this->getJson('/api/products?platform=steam&region=europe');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($match->slug));
        $this->assertFalse($slugs->contains($other->slug));
    }

    public function test_price_sorting_ignores_unavailable_products(): void
    {
        $unavailable = Product::factory()->create(['name' => 'No Keys']);
        $offer = Offer::factory()->for($unavailable)->create(['price_cents' => 100]);
        DigitalKey::factory()->for($offer)->sold()->create();

        $expensive = $this->purchasableProduct(['name' => 'Expensive'], 3000);
        $cheap = $this->purchasableProduct(['name' => 'Cheap'], 1500);

        $ascending = $this->getJson('/api/products?sort=price_asc');
        $descending = $this->getJson('/api/products?sort=price_desc');

        $ascending->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $cheap->slug)
            ->assertJsonPath('data.1.slug', $expensive->slug);

        $descending->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $expensive->slug)
            ->assertJsonPath('data.1.slug', $cheap->slug);
    }

    public function test_popularity_and_relevance_sort_by_popularity(): void
    {
        $low = $this->purchasableProduct(['name' => 'Low', 'popularity' => 10]);
        $high = $this->purchasableProduct(['name' => 'High', 'popularity' => 900]);

        foreach (['popularity', 'relevance'] as $sort) {
            $response = $this->getJson('/api/products?sort='.$sort);
            $response->assertOk()
                ->assertJsonPath('data.0.slug', $high->slug)
                ->assertJsonPath('data.1.slug', $low->slug);
        }
    }

    public function test_pagination_metadata_and_applied_filters_are_returned(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->purchasableProduct([
                'name' => 'Game '.$i,
                'platform' => 'Steam',
                'popularity' => $i,
            ], 1000 + $i);
        }

        $response = $this->getJson('/api/products?page=2&per_page=5&sort=price_asc&platform=Steam');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $response->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 5)
            ->assertJsonPath('meta.pagination.total', 15)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.from', 6)
            ->assertJsonPath('meta.pagination.to', 10)
            ->assertJsonPath('meta.applied_filters.sort', 'price_asc')
            ->assertJsonPath('meta.applied_filters.platform', 'Steam')
            ->assertJsonPath('meta.applied_filters.region', null)
            ->assertJsonPath('meta.applied_filters.per_page', 5);
    }

    public function test_invalid_parameters_return_validation_error_envelope(): void
    {
        $response = $this->getJson('/api/products?sort=bogus&per_page=49&min_price=100&max_price=50');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'The given parameters were invalid.')
            ->assertJsonStructure([
                'message',
                'code',
                'errors' => ['sort', 'per_page', 'max_price'],
            ]);
    }
}
