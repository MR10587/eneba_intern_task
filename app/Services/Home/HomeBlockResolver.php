<?php

namespace App\Services\Home;

/**
 * Contract for one homepage block type.
 *
 * WHY: HomeService can dispatch on `homepage_blocks.type` without knowing how
 * any block builds its data. Adding a new block type later means adding one
 * resolver, not changing the frontend or the endpoint.
 */
interface HomeBlockResolver
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function resolve(array $config): array;
}
