# API contract — Catalog + Authentication + Search + Homepage + Cart + Checkout/Orders slices (Phase 2)

Base URL: `http://127.0.0.1:8000/api` (Vite proxies `/api` to this during development).

Implemented:

- `GET /home`
- `GET /products/filters`
- `GET /products`
- `GET /products/{slug}`
- `GET /search`
- `GET /cart` (token required)
- `POST /cart/items` (token required)
- `DELETE /cart/items/{offer}` (token required)
- `POST /checkout` (token required)
- `GET /orders` (token required)
- `GET /orders/{id}` (token required)
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout` (token required)
- `GET /auth/me` (token required)

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

`errors` is present only for validation failures. Currently used codes:

| Code | Status | Where |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Endpoints with request bodies/parameters |
| `PRODUCT_NOT_FOUND` | 404 | Catalog detail, cart add, checkout |
| `OFFER_NOT_FOUND` | 404 | Cart add, checkout |
| `NOT_FOUND` | 404 | Cart delete, order detail |
| `INSUFFICIENT_INVENTORY` | 409 | Cart add/checkout when an offer is inactive or its keys are sold out |
| `PAYMENT_FAILED` | 402 | Checkout with the simulated declined card |
| `INVALID_CREDENTIALS` | 401 | Login |
| `UNAUTHENTICATED` | 401 | Protected routes without a valid token |

`FORBIDDEN` and `INTERNAL_ERROR` remain reserved; order ownership failures use
`NOT_FOUND` so one user cannot detect another user's order IDs.

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
never match it, and price sorts place it last. `relevance` aliases `popularity`
here because there is no search term; real typo-tolerant ranking lives in `GET /search`.

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

## `GET /search`

Query parameters:

| Param | Default | Rules |
| --- | --- | --- |
| `q` | required | Trimmed text, 2–64 characters |
| `page` | `1` | `>= 1` |
| `per_page` | `12` | `1–48` |

Matching is case-insensitive. On PostgreSQL it uses `pg_trgm` similarity plus a
substring match, so partial names (`Minec`) and typos (`mincraft`) both find
`Minecraft`. Results are ordered by relevance, then popularity, then slug.
The SQLite test database has no trigram functions, so it uses a portable `LIKE`
fallback: partial matches work, typo tolerance does not. This driver check keeps
the test suite runnable without PostgreSQL-specific SQL errors.

Search rows use the same `ProductResource` and pricing rule as `GET /products`:
only active products are returned, and `min_price_cents` comes from the cheapest
active offer with an available key. `offers` is not included in search results.

```json
{
  "data": [
    {
      "id": 1,
      "slug": "minecraft",
      "name": "Minecraft",
      "min_price_cents": 1999,
      "currency": "EUR",
      "available": true,
      "available_offer_count": 3
    }
  ],
  "meta": {
    "query": "mincraft",
    "result_count": 1,
    "pagination": {
      "current_page": 1, "per_page": 12, "total": 1,
      "last_page": 1, "from": 1, "to": 1
    }
  }
}
```

`meta.result_count` is the total number of matches across all pages, not just
the number of rows in `data`. Invalid parameters return the shared
`422 VALIDATION_ERROR` envelope with `errors.q` and/or `errors.per_page`.

## `GET /home`

Public endpoint. Returns only enabled `homepage_blocks` rows, ordered by
`position` then `id`. Each block's content is resolved server-side from its own
`type` and `config`, so the SPA renders generically and hardcodes no products,
headlines, or block data. Seed configuration arrives in Phase 4; until then
temporary DB rows are used for manual checks.

```json
{
  "data": [
    {
      "key": "demo-hero",
      "title": "Featured game",
      "type": "banner",
      "position": 0,
      "data": {
        "headline": "Featured this week",
        "subheadline": "A great price",
        "cta_label": "Buy now",
        "cta_href": "/products/demo-minecraft",
        "product": {
          "id": 2,
          "slug": "demo-minecraft",
          "name": "Minecraft",
          "min_price_cents": 1999,
          "currency": "EUR",
          "available": true,
          "available_offer_count": 1
        }
      }
    },
    {
      "key": "demo-best-sellers",
      "title": "Best sellers",
      "type": "best_sellers",
      "position": 1,
      "data": ["ProductResource summaries, popularity desc"]
    },
    {
      "key": "demo-deals",
      "title": "Deals",
      "type": "deals",
      "position": 2,
      "data": ["ProductResource summaries with backend-computed discount_percent"]
    }
  ],
  "meta": { "block_count": 3 }
}
```

`meta.block_count` is the number of enabled blocks, not the number of items
inside them.

Block config keys:

| Type | Config keys | Behavior |
| --- | --- | --- |
| `banner` | `product_slug`, `headline`, `subheadline`, `cta_label`, `cta_href` | Resolves an active product summary. Missing/inactive slug returns `"product": null`; the block still renders. No fallback slug exists in code. |
| `best_sellers` | `limit` (optional) | Active products ordered by popularity then slug. Default 8, clamped to 1–12. |
| `deals` | `limit` (optional) | Active products with a purchasable price and `compare_at_price_cents > min_price_cents`. Default 8, clamped to 1–12. |

Any other `type` returns `"data": []` without failing the request. Disabled
blocks are never returned.

## Authentication

Passwords are hashed with bcrypt. Registration and login issue a Laravel Sanctum
personal access token named `api`; only its hash is stored. Send it on protected
routes as `Authorization: Bearer <token>`. Email addresses are lowercased on input,
and there is no email verification or social login.

### `POST /auth/register`

Body: `{ "name": "...", "email": "...", "password": "min 8 chars", "password_confirmation": "..." }`

201 response:

```json
{
  "data": {
    "user": {
      "id": 1, "name": "Ada", "email": "ada@example.com",
      "email_verified_at": null, "created_at": "...", "updated_at": "..."
    },
    "token": "1|plainTextToken",
    "token_type": "Bearer"
  }
}
```

Duplicate email or invalid fields → `422 VALIDATION_ERROR` with `errors.email` /
`errors.password`. No token is issued on failure.

### `POST /auth/login`

Body: `{ "email": "...", "password": "..." }`. 200 response: identical `data` shape
to registration. Wrong password **or** unknown email → `401 INVALID_CREDENTIALS`:

```json
{ "message": "The provided credentials are incorrect.", "code": "INVALID_CREDENTIALS" }
```

### `POST /auth/logout` — token required

Deletes only the token used for the request, so other signed-in devices stay valid.

```json
{ "data": { "message": "Logged out successfully." } }
```

### `GET /auth/me` — token required

Returns the same `user` object shown above, directly under `data` (no token field).

### Missing or invalid token

Applies to `logout`, `me`, all cart endpoints, and all checkout/order endpoints:

```json
{ "message": "Authentication is required.", "code": "UNAUTHENTICATED" }
```

## Cart — token required

There is exactly one cart per user. It is always derived from the authenticated
user, so the client never sends a cart ID, user ID, price, or total. Quantity is
always `1` per offer and there is intentionally **no** quantity-update endpoint.

Only an **active product**, an **active offer**, and at least one **available
digital key** can be added. If an item becomes stale after it was added (key sold
or offer deactivated), it remains visible with `"available": false`; its price is
never silently changed. Checkout performs the authoritative recheck later.

### `GET /cart`

```json
{
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 2,
        "offer_id": 3,
        "quantity": 1,
        "product": { "...": "ProductResource summary" },
        "offer": { "...": "OfferResource" },
        "line_total_cents": 1999,
        "currency": "EUR",
        "available": true
      }
    ],
    "total_cents": 1999,
    "currency": "EUR"
  },
  "meta": { "item_count": 1, "available_item_count": 1 }
}
```

`total_cents` is the sum of all line totals (including stale items).
`available_item_count` lets the SPA warn about stale items without rechecking stock.

### `POST /cart/items`

Body: `{ "product_id": 2, "offer_id": 3 }`. No other fields are accepted; any
client-sent `price_cents`, `total_cents`, or `quantity` is ignored.

- New item → `201` with the updated cart.
- Offer already in the cart → `200` with the unchanged cart (idempotent add).
- Missing/inactive product → `404 PRODUCT_NOT_FOUND`.
- Missing offer, or an offer belonging to a different product → `404 OFFER_NOT_FOUND`.
- Inactive offer or no available key → `409 INSUFFICIENT_INVENTORY`.

Validation of the two IDs uses the shared `422 VALIDATION_ERROR` envelope.

### `DELETE /cart/items/{offer}`

Deletes that offer from the current user's cart only and returns `200` with the
updated cart. An offer that is not in this user's cart returns
`404 NOT_FOUND`; another user's item is indistinguishable from a missing one.

## Checkout — token required

Checkout runs in one DB transaction. The prices are recomputed from the current
persisted `offers.price_cents`; no client-supplied price or total is accepted.
All cart items are revalidated against live state, so a stale cart item cannot
buy an inactive product or a sold-out offer.

The payment is simulated. A card number ending in `0002` returns
`402 PAYMENT_FAILED` before any write happens: no order row, no order item, no
sold key, and the cart is unchanged. A successful payment stores only the
masked last 4 digits in `payment_reference`; the full card number is never
persisted or logged.

### `POST /checkout`

Body: `{ "card_number": "4242 4242 4242 4242" }`.

`card_number` is required, must be a string of 13–19 digits after spaces and
dashes are stripped, and is never sent to a payment provider.

`201` response:

```json
{
  "data": {
    "id": 7,
    "order_id": 7,
    "status": "completed",
    "total_cents": 2998,
    "currency": "EUR",
    "payment_status": "succeeded",
    "payment_reference": "SIM-A1B2C3D4-4242",
    "created_at": "2026-11-24T21:30:00.000000Z",
    "items": [
      {
        "id": 12,
        "offer_id": 3,
        "product_id": 2,
        "product": { "id": 2, "slug": "minecraft", "name": "Minecraft" },
        "region": "Europe",
        "quantity": 1,
        "unit_price_cents": 1999,
        "line_total_cents": 1999,
        "digital_key": { "code": "KEY-ABCD-EFGH-IJKL" }
      },
      {
        "id": 13,
        "offer_id": 9,
        "product_id": 8,
        "product": { "id": 8, "slug": "among-us", "name": "Among Us" },
        "region": "Global",
        "quantity": 1,
        "unit_price_cents": 999,
        "line_total_cents": 999,
        "digital_key": { "code": "KEY-MNOP-QRST-UVWX" }
      }
    ]
  },
  "meta": { "keys_delivered": 2 }
}
```

On success, the order is created before its items, one available key per line
is marked `sold` (lowest key ID first), the paid cart lines are removed, and
the cart remains available for future purchases.

Errors:

- Empty cart or non-EUR/mixed currency → `422 VALIDATION_ERROR` with `errors.cart`.
- Invalid card number → `422 VALIDATION_ERROR` with `errors.card_number`.
- Card ending in `0002` → `402 PAYMENT_FAILED` with no order or key mutation.
- Inactive/missing product → `404 PRODUCT_NOT_FOUND`.
- Inactive offer or no available key → `409 INSUFFICIENT_INVENTORY`.

## Orders — token required

Every order lookup is scoped to the authenticated user. A missing order and
another user's order both return `404 NOT_FOUND`; there is deliberately no
`403 FORBIDDEN` response for ownership failures.

### `GET /orders`

Query parameters: `page` (`>= 1`, default `1`) and `per_page` (`1–48`, default
`12`). Orders are newest first.

The list includes purchased product summaries and purchase dates for order
history. Delivered key codes are omitted here; they appear only in checkout
and order detail responses.

```json
{
  "data": [
    {
      "id": 7,
      "order_id": 7,
      "status": "completed",
      "total_cents": 2998,
      "currency": "EUR",
      "payment_status": "succeeded",
      "payment_reference": "SIM-A1B2C3D4-4242",
      "created_at": "2026-11-24T21:30:00.000000Z",
      "items": [
        {
          "id": 12,
          "offer_id": 3,
          "product_id": 2,
          "product": { "id": 2, "slug": "minecraft", "name": "Minecraft" },
          "region": "Europe",
          "quantity": 1,
          "unit_price_cents": 1999,
          "line_total_cents": 1999
        }
      ]
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1, "per_page": 12, "total": 1,
      "last_page": 1, "from": 1, "to": 1
    }
  }
}
```

### `GET /orders/{id}`

Returns the same order shape with `digital_key.code` on each item, so the SPA
can display purchased keys from order history. `{id}` must be numeric; a
missing id or another user's id returns:

```json
{ "message": "The requested resource was not found.", "code": "NOT_FOUND" }
```
