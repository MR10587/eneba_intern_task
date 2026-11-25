<?php

namespace Tests\Feature;

use App\Models\DigitalKey;
use App\Models\HomepageBlock;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\CatalogData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for the deterministic store seeders.
 *
 * WHY: the SPA must be reviewable without hidden local data. These tests pin
 * idempotency and prove that the seeded catalog drives home, catalog, search,
 * cart, and checkout through the real API endpoints.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_catalog_sellers_keys_blocks_and_test_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(CatalogData::products()), Product::count());
        $this->assertSame(count(CatalogData::sellers()), Seller::count());
        $this->assertSame(count(CatalogData::offers()), Offer::count());
        $this->assertSame($this->expectedKeyCount(), DigitalKey::count());
        $this->assertSame(4, HomepageBlock::count());

        $this->assertDatabaseHas('products', ['slug' => 'minecraft', 'is_active' => true]);
        $this->assertDatabaseHas('products', ['slug' => 'hidden-legend', 'is_active' => false]);
        $this->assertDatabaseHas('sellers', ['name' => 'KeyKingdom', 'is_active' => true]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('Test User', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.email', 'test@example.com');

        $this->getJson('/api/products/filters')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EUR');

        $filters = $this->getJson('/api/products/filters')->json('data');

        foreach (['Steam', 'Epic Games', 'Xbox', 'PlayStation'] as $platform) {
            $this->assertContains($platform, $filters['platforms']);
        }

        foreach (['Europe', 'Global', 'US'] as $region) {
            $this->assertContains($region, $filters['regions']);
        }
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $productIds = Product::orderBy('id')->pluck('id')->all();
        $sellerIds = Seller::orderBy('id')->pluck('id')->all();
        $offerIds = Offer::orderBy('id')->pluck('id')->all();
        $keyIds = DigitalKey::orderBy('id')->pluck('id')->all();
        $blockIds = HomepageBlock::orderBy('id')->pluck('id')->all();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($productIds, Product::orderBy('id')->pluck('id')->all());
        $this->assertSame($sellerIds, Seller::orderBy('id')->pluck('id')->all());
        $this->assertSame($offerIds, Offer::orderBy('id')->pluck('id')->all());
        $this->assertSame($keyIds, DigitalKey::orderBy('id')->pluck('id')->all());
        $this->assertSame($blockIds, HomepageBlock::orderBy('id')->pluck('id')->all());
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }

    public function test_home_uses_seeded_blocks_and_backend_resolved_products(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expectedDeals = collect(CatalogData::products())
            ->filter(fn (array $product): bool => $product['is_active']
                && $product['compare_at_price_cents'] !== null
                && $product['compare_at_price_cents'] > $product['price_cents'])
            ->count();

        $response = $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.block_count', 3)
            ->assertJsonPath('data.0.key', 'home-banner')
            ->assertJsonPath('data.0.type', 'banner')
            ->assertJsonPath('data.0.data.product.slug', 'minecraft')
            ->assertJsonPath('data.1.key', 'best-sellers')
            ->assertJsonPath('data.2.key', 'deals');

        $response->assertJsonCount(8, 'data.1.data');
        // The seeded deals block is configured with a limit of 8, so extra
        // discounted shelves do not change the homepage payload size.
        $response->assertJsonCount(min($expectedDeals, 8), 'data.2.data');
        $this->assertContains('minecraft', collect($response->json('data.2.data'))->pluck('slug')->all());
    }

    public function test_catalog_and_search_see_seeded_products_but_not_inactive_products(): void
    {
        $this->seed(DatabaseSeeder::class);

        $catalog = $this->getJson('/api/products?per_page=48')->assertOk();
        $slugs = collect($catalog->json('data'))->pluck('slug')->all();

        $this->assertSame($this->purchasableProductCount(), count($slugs));
        $this->assertContains('minecraft', $slugs);
        $this->assertNotContains('hidden-legend', $slugs);

        $this->getJson('/api/search?q=minec')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'minecraft')
            ->assertJsonPath('data.0.currency', 'EUR')
            ->assertJsonPath('meta.result_count', 1);

        $hiddenSearch = $this->getJson('/api/search?q=hidden')->assertOk();
        $this->assertNotContains(
            'hidden-legend',
            collect($hiddenSearch->json('data'))->pluck('slug')->all(),
        );
    }

    public function test_seeded_non_game_shelves_are_filterable_and_purchasable(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['Gift Cards' => 3, 'Top-Ups' => 2, 'Hardware' => 2] as $category => $expectedCount) {
            $response = $this->getJson(
                '/api/products?'.http_build_query(['category' => $category, 'per_page' => 48]),
            )
                ->assertOk()
                ->assertJsonPath('meta.pagination.total', $expectedCount)
                ->assertJsonPath('meta.applied_filters.category', $category)
                ->assertJsonPath('data.0.category', $category);

            $this->assertTrue(
                collect($response->json('data'))->every(fn (array $product): bool => $product['available']),
            );
        }
    }

    public function test_sold_out_and_inactive_seeded_offers_do_not_lower_computed_prices(): void
    {
        $this->seed(DatabaseSeeder::class);

        $products = collect($this->getJson('/api/products?per_page=48')->assertOk()->json('data'))
            ->keyBy('slug');

        $this->assertSame(4999, $products['fable']['min_price_cents']);
        $this->assertSame(1499, $products['gears-5']['min_price_cents']);
        $this->assertArrayNotHasKey('retro-classic-pack', $products->all());

        $retroDetail = $this->getJson('/api/products/retro-classic-pack')->assertOk();
        $this->assertFalse($retroDetail->json('data.available'));

        $gears = $this->getJson('/api/products/gears-5')->assertOk();
        $globalOffer = collect($gears->json('data.offers'))->firstWhere('region', 'Global');

        $this->assertNotNull($globalOffer);
        $this->assertFalse($globalOffer['available']);
    }

    public function test_seeded_offer_can_be_added_to_cart_and_checked_out(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->tokenForTestUser();
        $offer = $this->offer('minecraft', 'KeyKingdom', 'Europe');

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $offer->product_id,
            'offer_id' => $offer->id,
        ])->assertCreated();

        $definition = collect(CatalogData::offers())->first(fn (array $item): bool => $item['code'] === CatalogData::offerCode('minecraft', 'KK', 'EU'));
        $expectedKeyCode = CatalogData::keyCodes($definition)[0];

        $this->withToken($token)->postJson('/api/checkout', [
            'card_number' => '4242424242424242',
        ])
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 1999)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.items.0.digital_key.code', $expectedKeyCode);

        $this->assertDatabaseHas('digital_keys', ['code' => $expectedKeyCode, 'status' => 'sold']);
    }

    public function test_failed_payment_against_seeded_data_consumes_no_key(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->tokenForTestUser();
        $offer = $this->offer('cyberpunk-2077', 'KeyKingdom', 'Europe');
        $statusesBefore = DigitalKey::where('offer_id', $offer->id)->orderBy('id')->pluck('status', 'code')->all();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $offer->product_id,
            'offer_id' => $offer->id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/checkout', [
            'card_number' => '4000000000000002',
        ])
            ->assertStatus(402)
            ->assertJsonPath('code', 'PAYMENT_FAILED');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(
            $statusesBefore,
            DigitalKey::where('offer_id', $offer->id)->orderBy('id')->pluck('status', 'code')->all(),
        );
    }

    private function purchasableProductCount(): int
    {
        return Product::query()
            ->where('products.is_active', true)
            ->whereHas('offers', function ($offers): void {
                $offers->where('offers.is_active', true)
                    ->whereHas('digitalKeys', function ($keys): void {
                        $keys->where('digital_keys.status', 'available');
                    });
            })
            ->count();
    }

    private function expectedKeyCount(): int
    {
        return array_sum(array_column(CatalogData::offers(), 'key_count'));
    }

    private function tokenForTestUser(): string
    {
        return User::where('email', 'test@example.com')->firstOrFail()->createToken('api')->plainTextToken;
    }

    private function offer(string $slug, string $sellerName, string $region): Offer
    {
        return Offer::query()
            ->where('product_id', Product::where('slug', $slug)->value('id'))
            ->where('seller_id', Seller::where('name', $sellerName)->value('id'))
            ->where('region', $region)
            ->firstOrFail();
    }
}
