<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PostgreSQL-only coverage for the pg_trgm typo tolerance promised by SPEC.
 *
 * WHY: the default suite uses SQLite, which has no similarity() function.
 * These tests skip there and become active when phpunit.pgsql.xml is used, so
 * a regression in the real production path is visible in CI/local verification.
 */
class PostgresSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_trgm typo matching requires PostgreSQL.');
        }
    }

    #[DataProvider('typoQueries')]
    public function test_postgres_trgm_matches_minecraft_typos(string $query): void
    {
        $product = Product::factory()->create(['name' => 'Minecraft']);
        $offer = Offer::factory()->for($product)->create(['price_cents' => 1999]);
        DigitalKey::factory()->for($offer)->create();

        $this->getJson('/api/search?q='.urlencode($query))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // WHY: "mincraft" has no ILIKE hit; only trigram similarity finds it.
            ->assertJsonPath('data.0.slug', $product->slug)
            ->assertJsonPath('meta.query', $query)
            ->assertJsonPath('meta.result_count', 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function typoQueries(): array
    {
        return [
            'exact name' => ['Minecraft'],
            'partial name' => ['Minec'],
            'typo without substring' => ['mincraft'],
        ];
    }
}
