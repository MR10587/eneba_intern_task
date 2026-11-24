<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Homepage block limits
    |--------------------------------------------------------------------------
    |
    | These are server-side safety limits, not content. Actual block content
    | and product selection always come from homepage_blocks.config so the SPA
    | never hardcodes what appears on the homepage.
    |
    */
    'best_sellers_limit' => 8,
    'deals_limit' => 8,
    'max_items' => 12,
];
