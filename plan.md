# Implementation Plan — Eneba-style Video Game Key Storefront

## Confirmed decisions

- Laravel API (single backend) + separate Vite React/TypeScript SPA; Sanctum token auth.
- Laravel Sail / Docker Compose with PostgreSQL for local development.
- Typo-tolerant search via PostgreSQL `pg_trgm` (no external search service, no AI).
- Deterministic simulated payments: card ending `0002` fails, all others succeed.
- No hardcoded frontend data; all prices and totals computed by the backend.
- No microservices, seller/admin dashboards, social login, email verification, refunds, or mobile app.

## Target layout

Laravel stays at the repo root so Sail works with defaults; the SPA is a separate package in `frontend/`.

```
/
├─ docker-compose.yml          # Sail: app (PHP/Laravel) + pgsql
├─ app/ bootstrap/ config/ database/ routes/ tests/   # Laravel
├─ frontend/                   # Vite + React + TS (own package.json)
│  └─ src/{api,types,components,pages,styles}
├─ public/                     # Laravel web root; built SPA assets served here
├─ SPEC.md  AGENTS.md  CHAT_HISTORY.md  README.md  plan.md
```

Dev flow: Sail containers serve the API on `localhost`; Vite dev server proxies `/api` to it. For full-flow verification, build the SPA into `public/` and use Laravel's catch-all route so everything runs on one origin.

## Phase 0 — Scaffolding and toolchain

1. Initialise Laravel in place without disturbing the existing docs: `composer create-project laravel/laravel .` (preserve `SPEC.md`, `AGENTS.md`, `CHAT_HISTORY.md`).
2. Add Sanctum via `php artisan install:api` (or install per the Laravel version actually resolved).
3. Configure `.env` / `docker-compose.yml` for PostgreSQL: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, matching credentials.
4. Add a migration enabling `pg_trgm` (guarded so a restricted role does not break `migrate:fresh`).
5. Scaffold the SPA: `npm create vite@latest frontend -- --template react-ts`; add `react-router-dom`, typed fetch wrapper, `vitest`, `@testing-library/react`, `jsdom`, ESLint + Prettier.
6. Add convenience scripts (Sail aliases, `npm run dev/build/test/typecheck/lint`). Record the exact working commands in `AGENTS.md` — right now it explicitly notes none exist.

**Exit:** `sail up -d`, `sail artisan migrate`, and `npm run dev` all work; `AGENTS.md` has real commands.

## Phase 1 — Database schema and migrations

- `users` — id, name, email (unique), password.
- `products` — id, slug (unique), name, description, platform, genre, release_date, image_path, popularity, `compare_at_price_cents` (nullable, for deals), is_active, timestamps.
- `sellers` — id, name, is_active.
- `offers` — id, product_id FK, seller_id FK, region, `price_cents`, currency, is_active; index on `(product_id, is_active)`; guard against duplicate product/seller/region offers.
- `digital_keys` — id, offer_id FK, code (unique), status (`available`/`sold`).
- `carts` / `cart_items` — one cart per user; unique `(cart_id, offer_id)`; positive quantity.
- `orders` — id, user_id FK, status (`pending`/`completed`/`failed`), `total_cents`, currency, payment_status, payment_reference, timestamps.
- `order_items` — id, order_id FK, offer_id FK, quantity, `unit_price_cents`, `digital_key_id` FK **unique** (enforces one key → one order item).
- `homepage_blocks` — id, key (unique), title, type (`banner`/`best_sellers`/`deals`), `config` jsonb, position, is_enabled. This is what makes blocks independently configurable without an admin UI (seed data is the configuration).
- Sanctum `personal_access_tokens`.

Money is stored as integer cents everywhere; never trust client-sent prices.

**Exit:** `migrate:fresh --seed` succeeds and the uniqueness constraints exist.

## Phase 2 — Backend API

Thin controllers + FormRequests + service classes + API Resources + policies for ownership.

Routes (all under `/api`):

| Route | Purpose |
| --- | --- |
| `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` | Email/password auth, user info + status |
| `GET /home` | Enabled blocks in position order, each with its own fetched data |
| `GET /products` | Filters (platform, region, min/max price), sort (`price_asc`, `price_desc`, `popularity`, `relevance`), pagination (page, per_page); returns items, pagination metadata, and applied filters; active products only |
| `GET /products/{slug}` | Details + available seller offers with prices and regions |
| `GET /search?q=` | Typo/partial-tolerant matches + result count |
| `GET /cart`, `POST /cart/items`, `PATCH /cart/items/{offer}`, `DELETE /cart/items/{offer}` | Per-user cart; line prices and total from backend |
| `POST /checkout` | Auth required; simulated payment; order ID, status, summary |
| `GET /orders`, `GET /orders/{id}` | Signed-in user's own orders only |

Search: GIN trigram index on `products.name`; rank by `similarity(name, ?)` with an `ILIKE '%term%'` fallback so `Minecraft`, `Minec`, and `mincraft` all match.

Checkout transaction (the highest-risk part):

1. Require auth; validate request.
2. Open `DB::transaction`, lock the user's cart and the selected offer rows with `lockForUpdate`.
3. Re-check product/offer active state and that enough keys are available (`insufficient inventory` error otherwise).
4. Compute every price and the total in the backend from persisted offer prices.
5. Simulate payment: last 4 digits `0002` → `payment_failed`; otherwise success.
6. On success: create a completed order, allocate one available key per unit using `FOR UPDATE SKIP LOCKED`, create order items with the unique key FK, mark keys sold, clear the cart.
7. On failure: create no completed order and deliver no keys; return the specified error shape.
8. Map errors to the spec's categories: validation, authentication, product/offer not found, payment failure, insufficient inventory.

**Exit:** feature tests cover every row in the table above and every business rule.

## Phase 3 — Frontend SPA

- Typed API client with DTOs for every endpoint; zero product/offer/block literals in components.
- React Router pages: Home, Store, Search results, Product, Cart, Login, Register, Checkout, Order confirmation, Order history.
- Persistent header search on every page routed to `/search?q=`.
- Home renders `/home` blocks generically by `type` — adding a block requires no frontend change.
- Store page drives filters, sort, and pagination entirely from API-supplied values and metadata.
- Product page lists offers with price/region/availability and selects by `product_id` + `offer_id`.
- Cart shows API-computed line prices and total with add/remove/quantity controls.
- Checkout shows the backend order summary, a simulated card form, and a visible note that `0002` fails.
- Route guards redirect unauthenticated users from checkout and order history.
- Eneba-style design tokens (dark theme, accent gradients, spacing scale, component library) derived from the design reference; responsive layouts; loading, empty, and error states.

**Exit:** the full flow is clickable against seeded data with no frontend data duplication.

## Phase 4 — Seed data and styling

- Seeders create ~30 recognisable games across Steam/Epic/Xbox/PlayStation, regions (Europe, Global, etc.), several sellers, multiple offers per product, real key codes, and homepage block configuration.
- Include at least one deal product (`compare_at_price_cents`) and one out-of-stock offer to exercise error states.
- Deterministic seeds so tests are repeatable.

## Phase 5 — Verification

Command order once tooling exists (to be copied into `AGENTS.md` with exact commands):

1. `sail artisan migrate:fresh --seed`
2. `sail artisan test` (focused: `sail artisan test --filter=CheckoutTest`)
3. `cd frontend && npm run lint && npm run typecheck && npm run test && npm run build`

Backend tests: registration/login; cart isolation between users; filter/sort/pagination metadata; typo search; failed payment creates no order/keys; successful payment assigns unique keys and clears cart; insufficient inventory; inactive products hidden; homepage block toggles; concurrent checkout cannot double-assign a key.

Frontend tests: API-driven home block rendering; catalog filter/sort/pagination controls; auth guard redirect; checkout failure and success states.

Manual full-flow checklist: Home → search (`mincraft`) → product + offers → add offer → register/sign in → checkout with failing card → retry with success card → order + key → order history.

## Phase 6 — Docs and handoff

- Write `README.md` with setup, env vars, commands, and the test card rule.
- Replace the "no commands yet" note in `AGENTS.md` with verified commands and add the Sail/Vite layout and checkout invariants.
- Keep `CHAT_HISTORY.md` current with decisions made during implementation.

## Out of scope

Real payments, AI, seller/admin dashboards, social login, email verification, refunds/disputes, native mobile, microservices, autocomplete (explicitly not MVP).
