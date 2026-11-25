<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Str;

/**
 * Deterministic source data for the store demo.
 *
 * WHY: seeders must be repeatable and reviewable. Keeping these arrays here
 * means every seeder shares one definition of a product, its starting price,
 * and the seller offers generated from it — without factories or randomness.
 */
final class CatalogData
{
    /**
     * @return array<int, array{name: string, short: string}>
     */
    public static function sellers(): array
    {
        return [
            ['name' => 'KeyKingdom', 'short' => 'KK'],
            ['name' => 'InstantKeys', 'short' => 'IK'],
            ['name' => 'GameVault', 'short' => 'GV'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            self::product('minecraft', 'Minecraft', 'Steam', 'Sandbox', '2011-11-18', 980, 1999, 2999),
            self::product('cyberpunk-2077', 'Cyberpunk 2077', 'Steam', 'RPG', '2020-12-10', 970, 3999, 4999),
            self::product('fortnite', 'Fortnite', 'Epic Games', 'Battle Royale', '2017-07-21', 960, 0),
            self::product('among-us', 'Among Us', 'Epic Games', 'Party', '2018-06-15', 700, 499),
            self::product('grand-theft-auto-v', 'Grand Theft Auto V', 'Steam', 'Action', '2013-09-17', 958, 2499, 3499),
            self::product('the-witcher-3', 'The Witcher 3: Wild Hunt', 'Steam', 'RPG', '2015-05-18', 930, 1499, 1999),
            self::product('red-dead-redemption-2', 'Red Dead Redemption 2', 'Steam', 'Action', '2018-10-26', 925, 2999),
            self::product('elden-ring', 'Elden Ring', 'Steam', 'RPG', '2022-02-24', 940, 4999, 5999),
            self::product('hogwarts-legacy', 'Hogwarts Legacy', 'Steam', 'RPG', '2023-02-07', 890, 3999),
            self::product('counter-strike-2', 'Counter-Strike 2', 'Steam', 'FPS', '2012-08-21', 950, 0),
            self::product('dota-2', 'Dota 2', 'Steam', 'Strategy', '2013-07-09', 900, 0),
            self::product('apex-legends', 'Apex Legends', 'Epic Games', 'FPS', '2019-02-04', 880, 0),
            self::product('valorant', 'Valorant', 'Epic Games', 'FPS', '2020-06-02', 870, 0),
            self::product('fall-guys', 'Fall Guys', 'Epic Games', 'Party', '2020-08-04', 760, 999),
            self::product('rocket-league', 'Rocket League', 'Epic Games', 'Sports', '2015-07-07', 800, 999),
            self::product('overwatch-2', 'Overwatch 2', 'PlayStation', 'FPS', '2022-10-04', 850, 0),
            self::product('halo-infinite', 'Halo Infinite', 'Xbox', 'FPS', '2021-12-08', 810, 1999),
            self::product('forza-horizon-5', 'Forza Horizon 5', 'Xbox', 'Racing', '2021-11-09', 820, 2999, 3999),
            self::product('fable', 'Fable', 'Xbox', 'RPG', '2024-10-22', 780, 4999),
            self::product('gears-5', 'Gears 5', 'Xbox', 'Action', '2019-09-06', 740, 1499),
            self::product('god-of-war', 'God of War', 'PlayStation', 'Action', '2018-04-20', 910, 2499),
            self::product('spider-man-remastered', 'Spider-Man Remastered', 'PlayStation', 'Action', '2020-11-12', 905, 2999),
            self::product('horizon-zero-dawn', 'Horizon Zero Dawn', 'PlayStation', 'RPG', '2017-02-28', 895, 1999),
            self::product('the-last-of-us-part-ii', 'The Last of Us Part II', 'PlayStation', 'Action', '2020-06-19', 920, 3999),
            self::product('ghost-of-tsushima', 'Ghost of Tsushima', 'PlayStation', 'Action', '2020-07-17', 915, 3499),
            self::product('stardew-valley', 'Stardew Valley', 'Steam', 'Simulation', '2016-02-26', 830, 749),
            self::product('terraria', 'Terraria', 'Steam', 'Sandbox', '2011-05-16', 840, 999),
            self::product('hollow-knight', 'Hollow Knight', 'Steam', 'Platformer', '2017-02-24', 860, 1249),
            self::product('cuphead', 'Cuphead', 'Xbox', 'Platformer', '2017-09-29', 770, 1249),
            self::product('dead-cells', 'Dead Cells', 'Steam', 'Platformer', '2018-08-07', 750, 1499),
            self::product('hidden-legend', 'Hidden Legend', 'Epic Games', 'RPG', '2024-01-15', 100, 1999, null, false),
            self::product('retro-classic-pack', 'Retro Classic Pack', 'PlayStation', 'Collection', '2010-06-01', 200, 999),

            // Extra shelves so every storefront category has purchasable data.
            self::product('steam-gift-card-20', 'Steam Gift Card €20', 'Steam', 'Gift Card', '2024-01-01', 760, 2000, 2500, true, 'Gift Cards'),
            self::product('playstation-gift-card-50', 'PlayStation Store Gift Card €50', 'PlayStation', 'Gift Card', '2024-01-01', 750, 5000, null, true, 'Gift Cards'),
            self::product('xbox-gift-card-25', 'Xbox Gift Card €25', 'Xbox', 'Gift Card', '2024-01-01', 740, 2500, 3000, true, 'Gift Cards'),
            self::product('mobile-top-up-10', 'Mobile Top-Up €10', 'Mobile', 'Top-Up', '2024-02-01', 720, 1000, 1200, true, 'Top-Ups'),
            self::product('mobile-top-up-20', 'Mobile Top-Up €20', 'Mobile', 'Top-Up', '2024-02-01', 710, 2000, null, true, 'Top-Ups'),
            self::product('wireless-controller', 'Wireless Controller', 'Hardware', 'Accessory', '2023-06-01', 700, 5999, 6999, true, 'Hardware'),
            self::product('gaming-headset', 'Gaming Headset', 'Hardware', 'Accessory', '2023-06-01', 690, 7999, null, true, 'Hardware'),
        ];
    }

    /**
     * Build the full offer set from the product list.
     *
     * The first 20 products get three seller/region variants so every region
     * filter has results. The rest get two. Two deliberate edge cases are
     * included: an inactive offer and an offer whose keys are all sold.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function offers(): array
    {
        $variants = [
            ['seller' => 'KeyKingdom', 'region' => 'Europe', 'code' => 'EU', 'offset' => 0, 'keys' => 5],
            ['seller' => 'InstantKeys', 'region' => 'Global', 'code' => 'GL', 'offset' => 300, 'keys' => 4],
            ['seller' => 'GameVault', 'region' => 'US', 'code' => 'US', 'offset' => 600, 'keys' => 3],
        ];

        $sellerShortCodes = [];
        foreach (self::sellers() as $seller) {
            $sellerShortCodes[$seller['name']] = $seller['short'];
        }

        $offers = [];

        foreach (self::products() as $index => $product) {
            $productVariants = $index < 20 ? $variants : array_slice($variants, 0, 2);

            foreach ($productVariants as $variant) {
                $isActive = true;
                $keyCount = (int) $variant['keys'];
                $keyStatus = 'available';

                // This active product has no purchasable key, so the catalog
                // shows it with a null price instead of hiding it.
                if ($product['slug'] === 'retro-classic-pack') {
                    $keyCount = 1;
                    $keyStatus = 'sold';
                }

                if ($product['slug'] === 'gears-5' && $variant['seller'] === 'InstantKeys') {
                    $keyCount = 1;
                    $keyStatus = 'sold';
                }

                if ($product['slug'] === 'fable' && $variant['seller'] === 'InstantKeys') {
                    $isActive = false;
                }

                $sellerShort = $sellerShortCodes[$variant['seller']];
                $code = self::offerCode($product['slug'], $sellerShort, $variant['code']);

                $offers[] = [
                    'product_slug' => $product['slug'],
                    'seller_name' => $variant['seller'],
                    'seller_short' => $sellerShort,
                    'region' => $variant['region'],
                    'region_code' => $variant['code'],
                    'price_cents' => (int) $product['price_cents'] + (int) $variant['offset'],
                    'is_active' => $isActive,
                    'key_count' => $keyCount,
                    'key_status' => $keyStatus,
                    'code' => $code,
                ];
            }
        }

        return $offers;
    }

    public static function offerCode(string $productSlug, string $sellerShort, string $regionCode): string
    {
        return 'SEED-'.Str::upper($productSlug).'-'.$sellerShort.'-'.$regionCode;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<int, string>
     */
    public static function keyCodes(array $offer): array
    {
        $codes = [];

        for ($index = 1; $index <= (int) $offer['key_count']; $index++) {
            $codes[] = sprintf('%s-%02d', $offer['code'], $index);
        }

        return $codes;
    }

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     platform: string,
     *     category: string,
     *     genre: string,
     *     release_date: string,
     *     popularity: int,
     *     price_cents: int,
     *     compare_at_price_cents: ?int,
     *     is_active: bool
     * }
     */
    private static function product(
        string $slug,
        string $name,
        string $platform,
        string $genre,
        string $releaseDate,
        int $popularity,
        int $priceCents,
        ?int $compareAtPriceCents = null,
        bool $isActive = true,
        string $category = 'Games',
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'platform' => $platform,
            'category' => $category,
            'genre' => $genre,
            'release_date' => $releaseDate,
            'popularity' => $popularity,
            'price_cents' => $priceCents,
            'compare_at_price_cents' => $compareAtPriceCents,
            'is_active' => $isActive,
        ];
    }
}
