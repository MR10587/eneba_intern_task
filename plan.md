# Implementation Record — Eneba-style Digital Marketplace

This file records the application as it is implemented. It is not a future roadmap; setup, endpoints, data, and verification below all match the current repository.

## Current architecture

- Laravel 13 API at the repository root, PostgreSQL database, Sanctum personal access tokens.
- Separate React 19 + TypeScript + Vite SPA in `frontend/`, served by `npm run dev` on port 5173 and proxying `/api` to `http://127.0.0.1:8000`.
- Native PHP/Composer commands on Windows. There is no Docker/Sail configuration in use.
- One backend service per domain area; the homepage blocks are independently configured by database rows rather than separate services.
- All money is integer cents in EUR. The API computes prices, discounts, cart line totals, and order totals; the SPA only formats them.

## Implemented API

All routes are under `/api`.

| Route | Access | Purpose |
| --- | --- | --- |
| `GET /home` | public | Enabled homepage blocks in `position`, then `id` order; the banner features only a purchasable product |
| `GET /products/filters` | public | Backend-derived categories, platforms, regions, price bounds, currency |
| `GET /products` | public | Active products with category/platform/region/price filters, sorting, pagination, applied filters |
| `GET /products/{slug}` | public | Product details and active seller offers with availability |
| `GET /search` | public | Typo/partial-tolerant product search with the same filters and pricing as catalog |
| `POST /auth/register` | public | Email/password registration; returns a Sanctum token |
| `POST /auth/login` | public | Email/password login; returns a Sanctum token |
| `POST /auth/logout` | token | Revokes only the current token |
| `GET /auth/me` | token | Current user |
| `GET /cart` | public | Current cart (user or guest), line prices, total, and availability flags |
| `POST /cart/items` | public | Add an active, purchasable offer by `product_id` + `offer_id` |
| `DELETE /cart/items/{offer}` | public | Remove one of the current cart's lines |
| `POST /checkout` | token | Simulated payment, transactional key allocation, order creation |
| `GET /orders` | token | Current user's orders |
| `GET /orders/{id}` | token | Current user's order detail with the delivered key |

### Catalog and search filters

- `category`: one of `Games`, `Gift Cards`, `Top-Ups`, `Hardware`, `Best Deals`. `Best Deals` is derived from persisted `compare_at_price_cents > min_price_cents`; the other four are stored on products.
- `platform`, `region`: case-insensitive exact matches.
- `min_price`, `max_price`: integer EUR cents; `max_price >= min_price`.
- `sort`: `price_asc`, `price_desc`, `popularity`, `relevance`. `relevance` aliases `popularity` on the catalog endpoint.
- `page`, `per_page`: pagination with `1–48` per page.
- `/search` accepts all of the above except `sort`, plus required `q`. Filters are applied before pagination, so `result_count`, pagination metadata, and the returned grid always describe the same set.

A purchasable offer is active and has at least one `available` digital key. `min_price_cents` is the cheapest such offer. Products with none are omitted from catalog, search, and home grids; their detail pages remain reachable and show sold-out offers as `available: false`.

### Search behaviour

PostgreSQL uses `pg_trgm` similarity plus a case-insensitive substring match, so `Minecraft`, `Minec`, and `mincraft` all match. SQLite tests use a `LIKE` fallback because it has no `similarity()` function; the PostgreSQL-only cases live in `tests/Feature/Api/PostgresSearchTest.php` and run with `phpunit.pgsql.xml`.

### Cart, checkout, and orders

- Cart identity comes from either `auth()->id()` or a server-issued guest token sent as `X-Cart-Token`; no client-supplied cart ID, user ID, price, or total is trusted. The token is echoed as `meta.cart_token` for anonymous requests.
- Login/register accepts the same token as `cart_token`, merges guest lines into the user's cart, and deletes the guest cart. Checkout/orders stay token-protected.
- Duplicate adds return `200`; new adds return `201`. Stale lines remain visible with `available: false` and are never silently repriced.
- Checkout rejects unavailable or stale cart lines. The SPA hides checkout controls when a line is unavailable and redirects direct `/checkout` visits back to `/cart`.
- Payment is simulated: a 13–19 digit card succeeds; a card ending in `0002` returns `402 PAYMENT_FAILED` before any write. Only masked last-four digits are persisted.
- A failed payment creates no order and consumes no key. Successful checkout allocates one available key per line inside a transaction with a conditional key update and the unique `order_items.digital_key_id` index.
- Orders are scoped to the authenticated user. Another user's order returns `404 NOT_FOUND`.

All errors use the coded envelope, including `VALIDATION_ERROR` (422), `UNAUTHENTICATED` (401), `INVALID_CREDENTIALS` (401), `PRODUCT_NOT_FOUND` (404), `OFFER_NOT_FOUND` (404), `NOT_FOUND` (404), `INSUFFICIENT_INVENTORY` (409), and `PAYMENT_FAILED` (402).

## Implemented SPA

Routes:

- `/` — live purple marketplace backed by `/home`, `/products`, `/search`, `/products/filters`, `/products/:slug`, and `/cart/items`; category buttons, search, filters, and pagination all hit the API.
- `/browse`, `/home` — API-backed browsing homepage.
- `/products`, `/products/:slug` — catalog filters/sort/pagination and seller offer selection.
- `/search` — backend search results with result count.
- `/login`, `/register` — token auth forms.
- `/cart` — public cart with backend-computed totals; guests see a sign-in reminder at checkout.
- `/checkout`, `/orders`, `/orders/:id` — signed-in purchase flow. Unavailable cart lines are named and block checkout.
- `*` — not-found page.

The auth token is persisted client-side through `AuthContext`; the guest cart token is stored under `eneba_cart_token` and sent on cart requests. Home blocks are rendered generically by `type`, so adding a configured block does not require frontend changes. Cart drawer focus trapping, body scroll lock, Escape close, category `aria-pressed`, clamped price inputs, toasts, loading, empty, and error states are implemented.

## Seed data

`DatabaseSeeder` is idempotent by natural keys and creates the demo user `test@example.com` / `password`, 3 sellers, 39 products, 98 offers, 401 digital keys, and 4 homepage blocks (one disabled). Product shelves cover games, gift cards, top-ups, and hardware; seeded deals and sold-out/inactive edge cases exercise backend pricing and error paths. Re-running the seeder never reassigns a key already used by an order.

## Verification commands

From the repository root in PowerShell:

```powershell
& 'C:\php\php.exe' artisan test
& 'C:\php\php.exe' vendor/bin/pint --test
& 'C:\php\php.exe' vendor/bin/phpunit -c phpunit.pgsql.xml
& 'C:\php\php.exe' artisan route:list --path=api
```

The PostgreSQL configuration uses the separate `eneba_store_test` database and enables the three `mincraft` typo cases. The default `phpunit.xml` uses in-memory SQLite and skips those cases cleanly.

From `frontend/`:

```powershell
npm run typecheck
npm test -- --run
npm run lint
npm run format:check
npm run build
```

Manual acceptance flow: `/` → `/products/:slug` → add offer as a guest → `/cart` → select checkout → `/login` or `/register` (guest lines are merged) → `/checkout` with `4242 4242 4242 0002` for failure or `4242 4242 4242 4242` for success → `/orders/:id` with delivered key → `/orders`.

## Current boundaries

No real payment processing, AI integration, seller/admin dashboards, refunds/disputes, native mobile app, microservices, social login, email verification, seller registration, autocomplete, or copied Eneba artwork/logo.
