# API contract — Catalog slice (Phase 2, first slice)

Base URL: `http://127.0.0.1:8000/api` (Vite proxies `/api` to this during development).

Implemented:

- `GET /products/filters`
- `GET /products`
- `GET /products/{slug}`

Authentication, search, homepage, cart, checkout and orders are **not implemented yet**.

All prices are **integer cents in EUR**. The `currency` field is always `"EUR"`.
The client must format cents for display; it must never compute or alter prices.

## Response envelope

```json
{ "data": "<resource or array>", "meta": { "...": "..." } }
```

`meta` is omitted when empty.

## Error envelope

```json
{ "message": "The given parameters were invalid.", "code": "VALIDATION_ERROR", "errors": { "sort": ["..."] } }
```

`errors` is present only for validation failures. Catalog uses `VALIDATION_ERROR` (422)
and `PRODUCT_NOT_FOUND` (404). Other codes are reserved for later slices.

## `GET /products/filters`

Returns backend-derived options so the SPA has no hardcoded catalog values.
Only purchasable offers (active product, active offer, ≥1 available key) contribute.

```json
{
  "data": {
    "platforms": ["Steam", "Epic Games", "Xbox", "PlayStation"],
    "regions": ["Europe", "Global", "US"],
    "price_range": { "min_cents": 499, "max_cents": 5999 },
    "currency": "EUR"
  }
}
```

## `GET /products`

Query parameters:

| Param | Default | Rules |
| --- | --- | --- |
| `platform` | null | Case-insensitive exact match |
| `region` | null | Case-insensitive exact match on active offers |
| `min_price` | null | Integer EUR cents, `>= 0` |
| `max_price` | null | Integer EUR cents, `>= min_price` |
| `sort` | `popularity` | `price_asc`, `price_desc`, `popularity`, `relevance` |
| `page` | `1` | `>= 1` |
| `per_page` | `12` | `1–48` |

Unknown parameters are ignored. All filters are ANDed.

Pricing rule: `min_price_cents` is the cheapest **active** offer that still has at least
one `available` digital key. A product with no such offer remains visible with
`min_price_cents: null`, `available: false`, and `available_offer_count: 0`; price filters
never match it, and price sorts place it last. `relevance` currently aliases `popularity`
because there is no search term on this endpoint.

```json
{
  "data": [
    {
      "id": 1,
      "slug": "minecraft",
      "name": "Minecraft",
      "description": "Build anything...",
      "platform": "Steam",
      "genre": "Sandbox",
      "release_date": "2011-11-18",
      "image_path": null,
      "popularity": 980,
      "min_price_cents": 1999,
      "compare_at_price_cents": 2999,
      "discount_percent": 33,
      "currency": "EUR",
      "available": true,
      "available_offer_count": 3
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1, "per_page": 12, "total": 30,
      "last_page": 3, "from": 1, "to": 12
    },
    "applied_filters": {
      "platform": "Steam", "region": "Europe",
      "min_price_cents": 0, "max_price_cents": 1000,
      "sort": "price_asc", "page": 1, "per_page": 12
    }
  }
}
```

Validation example:

```json
{
  "message": "The given parameters were invalid.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "sort": ["The selected sort is invalid."],
    "per_page": ["The per page field must not be greater than 48."],
    "max_price": ["max_price must be greater than or equal to min_price."]
  }
}
```

## `GET /products/{slug}`

Returns one active product with **all active offers**, including sold-out ones flagged
`available: false`. Inactive offers are hidden. Offers are ordered by price, then region.
The buyer selects an offer by `product_id` + `offer_id`.

```json
{
  "data": {
    "id": 1,
    "slug": "minecraft",
    "name": "Minecraft",
    "description": "Build anything...",
    "platform": "Steam",
    "genre": "Sandbox",
    "release_date": "2011-11-18",
    "image_path": null,
    "popularity": 980,
    "min_price_cents": null,
    "compare_at_price_cents": 2999,
    "discount_percent": null,
    "currency": "EUR",
    "available": false,
    "available_offer_count": 0,
    "offers": [
      {
        "id": 10,
        "seller": { "id": 2, "name": "KeyKingdom" },
        "region": "Europe",
        "price_cents": 1999,
        "currency": "EUR",
        "available": false
      }
    ]
  },
  "meta": { "available_offer_count": 0 }
}
```

Missing or inactive slug:

```json
{ "message": "Product not found.", "code": "PRODUCT_NOT_FOUND" }
```
