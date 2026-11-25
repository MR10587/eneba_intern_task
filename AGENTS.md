# AGENTS.md

## Repo state

- Laravel backend scaffolded at the repo root: skeleton `v13.0.0`, framework `v13.43.0`. Existing docs (`SPEC.md`, `AGENTS.md`, `CHAT_HISTORY.md`, `plan.md`) were preserved.
- Separate `frontend/` Vite + React + TypeScript SPA is scaffolded; Laravel root remains API-only.
- Sanctum is installed (`personal_access_tokens` table, `HasApiTokens` on `User`). Domain schema and models exist: products, sellers, offers, digital_keys, carts, cart_items, orders, order_items, homepage_blocks.
- Phase 2 catalog slice implemented: `GET /api/products/filters`, `GET /api/products`, `GET /api/products/{slug}`, with coded/error envelopes, EUR cents, backend-computed prices, and 15 feature tests.
- Phase 2 authentication slice implemented: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` with Sanctum personal access tokens, bcrypt hashes, and 10 feature tests.
- Phase 2 search slice implemented: `GET /api/search` with driver-aware pg_trgm/LIKE matching, catalog pricing reuse, `meta.query`/`meta.result_count`, and 7 feature tests.
- Phase 2 homepage slice implemented: `GET /api/home` with DB-driven `homepage_blocks`, `banner`/`best_sellers`/`deals` resolvers, graceful unknown types, `meta.block_count`, and 8 feature tests.
- Phase 2 cart slice implemented: `GET /api/cart`, `POST /api/cart/items`, `DELETE /api/cart/items/{offer}` with guest-token/user scoping, backend-computed line totals, stale-item flags, coded errors, and 15 feature tests. Guests may shop anonymously; sign-in merges the guest cart and is required only for checkout/orders.
- Phase 2 checkout/orders slice implemented: `POST /api/checkout`, `GET /api/orders`, `GET /api/orders/{id}` with simulated payment, transactional key allocation, per-user order scoping, delivered key detail, coded errors, and 17 feature tests (12 checkout + 5 order). This completes the backend acceptance flow.
- Deterministic seed data implemented: 39 products, 3 sellers, 98 offers, 401 digital keys, 4 homepage blocks, and the `test@example.com` / `password` user. Products cover games, gift cards, top-ups, and hardware. Seeders are idempotent by natural keys and never reset a key already assigned to an order. 8 seeder tests cover idempotency, home/catalog/search, category shelves, unavailable offers, checkout success, and failed payment.
- Phase 3 slice 1 (frontend foundation + public browsing) implemented: typed API client/DTOs, `useAsync` request-state hook, dark Eneba shell, generic home block renderer, store with URL-driven filters/sort/page, typo-tolerant search results page, and shared UI primitives.
- Purple/midnight storefront at `/` uses Tailwind + Lucide and is now API-backed: `GET /home` supplies highlights, `GET /products` / `/search` supply the live grid, filter options come from `/products/filters`, and add-to-cart uses the public cart (guests included). No mock catalog or wishlist UI remains.
- Phase 3 slice 2 (purchase flow) implemented in the same visual system: persisted Sanctum token + `AuthContext`, `RequireAuth` for checkout/orders only, product detail with seller offers, public cart, login/register, simulated checkout, order confirmation with delivered keys, and order history. Auth guard, guest add, cart remove, checkout success/failure, delivered key, and order history tests added.
- `SPEC.md` is the product source of truth; `plan.md` is an implementation record containing only current features and verified commands.
- `README.md` and `frontend/README.md` are project-specific handoff docs covering setup, env vars, native Windows commands, demo credentials, the `0002` test card, routes, and verification. Root `.env.example` uses `APP_NAME=Eneba`.
- Final polish completed: cart drawer traps focus, locks body scroll, restores focus, and closes on Escape; category filters use `aria-pressed`; catalog price sliders clamp `min <= max`; stale API-contract wording and unused Vite template assets were removed.
- Add-to-cart feedback uses an app-wide toast (`context/ToastProvider.tsx`, `components/ui/ToastViewport.tsx`) showing “added to cart. Ready for checkout.” with a `View cart` action on the live storefront and a success toast on the product page. Guests can add without signing in; the cart and drawer remind them that sign-in happens at checkout.
- Marketplace category buttons (top nav, hero CTAs, footer links) change the filter and smooth-scroll to `#marketplace`. The header heart/wishlist control was removed at the user's request.
- Category filtering moved into the backend: `products.category` stores `Games` / `Gift Cards` / `Top-Ups` / `Hardware`, and `Best Deals` is derived from `compare_at_price_cents > min_price_cents`. `/products` and `/search` apply category, platform, region, and max price before pagination; the SPA no longer slices a fetched page.
- Catalog, search, and homepage grids exclude products with no purchasable offer; the SPA also filters defensively. Product detail still shows sold-out offers as `available: false`, and stale cart lines remain visible.
- Featured/banner products must be purchasable: `BannerBlockResolver` uses the configured slug while available, otherwise the most popular catalog product, and builds `cta_href` from the chosen product. With no purchasable product, the banner carries `product: null` and the SPA hides it. Seeded banner copy is generic rather than naming a specific game.
- Customer-facing copy contains no "API" or "backend" wording. Those terms are confined to code comments, tests, and developer docs.
- Array-valued `email` in register/login is type-guarded before normalisation, so it returns coded `422 VALIDATION_ERROR` instead of an `Array to string conversion` warning.
- Cart and cart drawer name unavailable lines and disable checkout; a direct `/checkout` visit with a stale line redirects back to `/cart`. Guest carts are keyed by an opaque `X-Cart-Token` header persisted as `eneba_cart_token`; login/register sends it as `cart_token`, merges the lines, and the SPA clears it.
- PostgreSQL typo coverage lives in `tests/Feature/Api/PostgresSearchTest.php` with PHPUnit attributes and runs via `phpunit.pgsql.xml` / `composer test:pgsql`; default SQLite runs skip those three cases cleanly.

## Windows environment (verified 2026-11-25)

- Windows 11. PHP 8.5.9 at `C:\php\php.exe`; Composer 2.11.2 at `C:\ProgramData\ComposerSetup\bin\composer.bat`. Neither is on `PATH`.
- For Composer in PowerShell, prefix the session PATH: `$env:PATH = 'C:\php;' + $env:PATH`, then call `composer`. Alternative without PATH changes: `& 'C:\php\php.exe' 'C:\ProgramData\ComposerSetup\bin\composer.phar' <args>`.
- Node 24.22.1 and npm 11.19.1 are installed at `C:\Program Files\nodejs\` and on `PATH`.
- Docker Desktop is not installed. Do not use Sail, `sail ...`, or `docker compose`; use native PHP commands. `plan.md` records the native setup only.
- Enabled in `C:\php\php.ini` (backup at `C:\php\php.ini.bak`): `fileinfo`, `intl`, `pdo_pgsql`, `pdo_sqlite`, `zip`. `intl` is required by Laravel's number formatting; without it artisan commands throw.
- The root `package.json` / `vite.config.js` belong to Laravel's Blade/Tailwind asset pipeline — do not add React there. The SPA is `frontend/`, on port 5173, proxying `/api` to `http://127.0.0.1:8000`.
- `.env` uses `DB_CONNECTION=pgsql`, host `127.0.0.1:5432`, database `eneba_store`, user `postgres`. Verified against PostgreSQL 18.6; all migrations are Ran. Native PostgreSQL is reached over localhost; no `docker-compose.yml` is used.
- `phpunit.xml` forces in-memory SQLite. Keep PostgreSQL-only SQL (the `pg_trgm` extension and GIN index) guarded by a driver check or tests will fail. `phpunit.pgsql.xml` uses the separate `eneba_store_test` database for the real trigram path.
- Frontend uses **oxlint + Prettier**, not ESLint. `typescript-eslint@8` declares `typescript <6.1.0` and cannot support the project's TypeScript 7.0.2; oxlint is the Vite 9 template linter and works. Prettier supplies formatting.

## Phase 0/1 audit (verified 2026-11-24)

- All Phase 0/1 schema entities exist in PostgreSQL with the expected columns, FKs, and unique indexes; `pg_trgm` extension and `products_name_trgm_idx` GIN index are present.
- `migrate:fresh --seed` succeeds on PostgreSQL and on in-memory SQLite. `DatabaseSeeder` creates the default `test@example.com` user plus the full deterministic catalog/seller/offer/key/homepage dataset; re-running it does not duplicate rows.
- `minecraft` / `minec` trigram scores and `ILIKE` hits verified; `mincraft` has similarity ~0.583 but no `ILIKE` hit, which is why Phase 2 search must rank by similarity rather than rely on `ILIKE` alone.
- Laravel 13's `Blueprint` has no `check()` method. Enumerated statuses (`available`/`sold`, `pending`/`completed`/`failed`) and `quantity > 0` are therefore enforced by FormRequest validation in Phase 2, not DB CHECK constraints.

## Phase 2 decisions (user-approved)

- Currency is **EUR**, stored and returned as integer cents; `config/catalog.php` holds the currency and pagination defaults. A non-destructive migration changed `offers` / `orders` currency defaults from USD to EUR.
- Cart quantity is always 1 per offer and the `PATCH /cart/items/{offer}` route was **removed**. Cart is derived from either `auth()->id()` or a server-issued guest token via `CartService`/`CartIdentity` — no cart ID from the client and no `CartPolicy`. Guest cart endpoints are public; checkout/orders remain behind `auth:sanctum`. Login/register optionally accepts `cart_token` and merges guest lines into the user cart. Duplicate add returns `200`; new add returns `201`. Stale items stay visible with `available: false`; prices are never silently changed. `OfferResource.available` requires both `is_active` and an available key.
- Filter options come from `GET /products/filters`; `relevance` sort aliases `popularity` on the catalog endpoint.
- Search (`GET /search`): pg_trgm similarity > 0.3 OR case-insensitive substring on PostgreSQL; SQLite tests use a LIKE fallback. Reuses `ProductResource` and catalog pricing; returns `meta.query` and `meta.result_count`.
- Homepage (`GET /home`): one public endpoint; enabled `homepage_blocks` ordered by `position`, then `id`. `banner`/`best_sellers`/`deals` resolvers read DB config; unknown types return `data: []`. `config/home.php` holds only item limits, never content.
- Checkout: no order row at all on payment failure; create the order before order items; re-check key status after `lockForUpdate()`; conditional `UPDATE ... WHERE status = 'available'` plus the unique `order_items.digital_key_id` index prevent double assignment.
- Simulated payment accepts a 13–19 digit `card_number` after stripping spaces/dashes; ending `0002` returns `402 PAYMENT_FAILED` before any write. Only masked last-4 is stored in `payment_reference`; the full card number is never persisted or logged.
- Orders are always scoped by `auth()->id()` in `OrderService`; there is no `OrderPolicy`. Another user's order returns `404 NOT_FOUND`, not `403`, so order existence is not leaked. `OrderResource` emits both `id` and an `order_id` alias, and exposes `digital_key` only on checkout/detail responses.
- Auth: Sanctum personal access tokens named `api`, returned once as `{token, token_type: "Bearer"}`. Wrong email/password uses a distinct `INVALID_CREDENTIALS` (401) code; missing token uses `UNAUTHENTICATED` (401). Duplicate email is a `VALIDATION_ERROR`. `logout` deletes only the current token. Emails are lowercased on input.

## Verified commands (PowerShell)

- `& 'C:\php\php.exe' artisan --version` → `Laravel Framework 13.43.0`
- `& 'C:\php\php.exe' artisan test` → 89 passed, 3 skipped (PostgreSQL-only), 690 assertions (2 defaults + 16 catalog + 5 detail + 9 search + 9 home + 15 cart + 12 checkout + 5 order + 11 auth + 8 seeder + 3 skipped pg_trgm)
- `& 'C:\php\php.exe' artisan route:list --path=api` → 15 routes (1 home + 3 catalog + 1 search + 3 cart + 3 checkout/orders + 4 auth)
- `& 'C:\php\php.exe' vendor/bin/pint --test` → passed
- `& 'C:\php\php.exe' vendor/bin/phpunit -c phpunit.pgsql.xml` → 92 passed, 705 assertions on `eneba_store_test` (includes three `mincraft` typo cases)
- `& 'C:\php\php.exe' 'C:\ProgramData\ComposerSetup\bin\composer.phar' validate --no-check-publish` → valid
- `& 'C:\php\php.exe' artisan migrate:status` → all migrations Ran; `artisan db:show` → PostgreSQL 18.6 / `eneba_store`
- `& 'C:\php\php.exe' artisan install:api --no-interaction` → Sanctum v4.5.0 installed and its migration ran
- `& 'C:\php\php.exe' artisan migrate --force` → initial 10 domain migrations applied; `pg_trgm` extension and `products_name_trgm_idx` verified present in PostgreSQL. Currency default and `products.category` migrations were applied separately and show Ran in `migrate:status`.
- `2026_11_24_150000_change_currency_defaults_to_eur` applied with `artisan migrate --force` (non-destructive default change; no data reset)
- `2026_11_25_120000_allow_guest_carts` applied with `artisan migrate --force` (nullable `carts.user_id`, unique `carts.guest_token`; existing user carts untouched)
- `& 'C:\php\php.exe' artisan migrate:fresh --seed --force --no-interaction` → succeeds on PostgreSQL; `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; & 'C:\php\php.exe' artisan migrate:fresh --force` → succeeds on SQLite
- Clean SQLite seed verification (temporary file, live `eneba_store` untouched): `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE='C:\Users\ramal\AppData\Local\Temp\opencode\seed-check.sqlite'; & 'C:\php\php.exe' artisan migrate --force; & 'C:\php\php.exe' artisan db:seed --force` → succeeds; running `db:seed` a second time succeeds with no duplicate rows.
- `$env:PATH = 'C:\php;' + $env:PATH; & 'C:\ProgramData\ComposerSetup\bin\composer.bat' check-platform-reqs` → all requirements satisfied
- `& 'C:\php\php.exe' artisan serve` and `npm run dev` in `frontend/` — both confirmed working by the user.
- In `frontend/`: `npm install`, `npm run typecheck`, `npm test -- --run`, `npm run lint`, `npm run format:check`, `npm run build` → all pass (Vitest 5.1.1, 45 tests across 16 files; Vite 8.3.8). Lint uses oxlint with no warnings.

## Hard constraints (from `SPEC.md`)

- No hardcoded frontend data in storefront pages. Backend content comes from endpoints; prices and totals are always computed by the backend, never in the client. `/` is a live API-backed storefront, and the old `frontend/src/data/games.ts` mock catalog has been removed.
- No real payment processing (simulated or provider test mode, with explicit success/failure outcomes) and no AI integration.
- Single PHP backend — the spec's "each block has its own service/data source" means independently configurable backend endpoints, not microservices (explicit non-goal).

## Acceptance flow

`/`, `/browse`, or `/search` → `/products/:slug` + seller offers → add offer via `product_id` + `offer_id` as a guest → `/cart` → checkout guard → `/login` or `/register` (guest lines merge) → `/checkout` → `/orders/:id` with delivered key → `/orders`. Eneba naming is used per explicit user request; no copied logo, artwork, or other proprietary assets.

## Easy-to-miss requirements

- Catalog: active products only; filters for category (including derived `Best Deals`), platform / region / price range; sorting by price ascending/descending, popularity, relevance; pagination with page number, page size, metadata, and applied filters echoed back. `/search` honours the same filters before pagination.
- Homepage: blocks (banner, best sellers, deals) independently configurable and fetched from backend endpoints.
- Search: typo- and partial-name-tolerant (`Minecraft`, `Minec`, `mincraft`); returns matches and result count. Autocomplete/correction is out of MVP.
- Product page: details plus multiple seller offers with price, region, and availability; selection is by product ID + offer ID.
- Cart: add/remove offers, update quantity if supported; return line prices and total; each user sees only their own cart.
- Auth: email + password only; no social login or email verification. Checkout and order history are signed-in only.
- Checkout: simulated payment method with success/failure simulation; return payment status, order ID, and order summary.
- Delivery & history: assign a digital key on success; show purchased products, order details, and purchase dates.

## Business rules easy to violate

- Users can purchase only available offers; a failed payment must not create a completed order or deliver a key.
- A digital key must never be assigned to more than one order.
- Model the specified errors: validation, authentication, product/offer not found, payment failure, insufficient inventory.

## Explicit non-goals

No seller registration/dashboard, admin dashboard (seed data is sufficient), refunds or disputes, native mobile app.

## Stack

- Frontend: React 19 + TypeScript 7 + Vite 8 in `frontend/`
- Backend: PHP 8.5.9 + Laravel 13.43.0
- Database: PostgreSQL 18.6 (`eneba_store`)
