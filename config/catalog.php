<?php

return [
    /**
     * All prices are integer cents. EUR was chosen explicitly for this
     * project; changing it here changes every API response consistently.
     */
    'currency' => env('CATALOG_CURRENCY', 'EUR'),

    /**
     * Catalog pagination defaults and hard upper bound.
     * WHY: an unbounded per_page would let a client pull the whole table.
     */
    'per_page' => 12,
    'max_per_page' => 48,
];
