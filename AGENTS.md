# AGENTS.md

## Repo state

- Laravel backend scaffolded at the repo root: skeleton `v13.0.0`, framework `v13.43.0`. Existing docs (`SPEC.md`, `AGENTS.md`, `CHAT_HISTORY.md`, `plan.md`) were preserved.
- Separate `frontend/` Vite + React + TypeScript SPA is scaffolded; Laravel root remains API-only.
- Sanctum is installed (`personal_access_tokens` table, `HasApiTokens` on `User`). Domain schema and models exist: products, sellers, offers, digital_keys, carts, cart_items, orders, order_items, homepage_blocks.
- Phase 2 catalog slice implemented: `GET /api/products/filters`, `GET /api/products`, `GET /api/products/{slug}`, with coded/error envelopes, EUR cents, backend-computed prices, and 15 feature tests.
- Phase 2 authentication slice implemented: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` with Sanctum personal access tokens, bcrypt hashes, and 10 feature tests.
- Phase 2 search slice implemented: `GET /api/search` with driver-aware pg_trgm/LIKE matching, catalog pricing reuse, `meta.query`/`meta.result_count`, and 7 feature tests. Homepage/cart/checkout/orders are not implemented yet.
- `SPEC.md` is the product source of truth; `plan.md` is the implementation plan.

## Windows environment (verified 2026-11-24)

- Windows 11. PHP 8.5.9 at `C:\php\php.exe`; Composer 2.11.2 at `C:\ProgramData\ComposerSetup\bin\composer.bat`. Neither is on `PATH`.
- For Composer in PowerShell, prefix the session PATH: `$env:PATH = 'C:\php;' + $env:PATH`, then call `composer`. Alternative without PATH changes: `& 'C:\php\php.exe' 'C:\ProgramData\ComposerSetup\bin\composer.phar' <args>`.
- Node 24.22.1 and npm 11.19.1 are installed at `C:\Program Files\nodejs\` and on `PATH`.
- Docker Desktop is not installed. Do not use Sail, `sail ...`, or `docker compose`; use native PHP commands. The Docker/Sail steps in `plan.md` are blocked until this changes.
- Enabled in `C:\php\php.ini` (backup at `C:\php\php.ini.bak`): `fileinfo`, `intl`, `pdo_pgsql`, `pdo_sqlite`, `zip`. `intl` is required by Laravel's number formatting; without it artisan commands throw.
- The root `package.json` / `vite.config.js` belong to Laravel's Blade/Tailwind asset pipeline — do not add React there. The SPA is `frontend/`, on port 5173, proxying `/api` to `http://127.0.0.1:8000`.
- `.env` uses `DB_CONNECTION=pgsql`, host `127.0.0.1:5432`, database `eneba_store`, user `postgres`. Verified against PostgreSQL 18.6; all migrations are Ran. Plan's `DB_HOST=pgsql` / `docker-compose.yml` are intentionally not used because Docker is unavailable; native PostgreSQL is reached over localhost.
- `phpunit.xml` forces in-memory SQLite. Keep PostgreSQL-only SQL (the `pg_trgm` extension and GIN index) guarded by a driver check or tests will fail.
- Frontend uses **oxlint + Prettier**, not ESLint. `typescript-eslint@8` declares `typescript <6.1.0` and cannot support the project's TypeScript 7.0.2; oxlint is the Vite 9 template linter and works. Prettier supplies formatting.

## Phase 0/1 audit (verified 2026-11-24)

- All Phase 0/1 schema entities exist in PostgreSQL with the expected columns, FKs, and unique indexes; `pg_trgm` extension and `products_name_trgm_idx` GIN index are present.
- `migrate:fresh --seed` succeeds on PostgreSQL and on in-memory SQLite. `DatabaseSeeder` currently creates only the default `test@example.com` user; product/offer/homepage seed data is Phase 4.
- `minecraft` / `minec` trigram scores and `ILIKE` hits verified; `mincraft` has similarity ~0.583 but no `ILIKE` hit, which is why Phase 2 search must rank by similarity rather than rely on `ILIKE` alone.
- Laravel 13's `Blueprint` has no `check()` method. Enumerated statuses (`available`/`sold`, `pending`/`completed`/`failed`) and `quantity > 0` are therefore enforced by FormRequest validation in Phase 2, not DB CHECK constraints.

## Phase 2 decisions (user-approved)

- Currency is **EUR**, stored and returned as integer cents; `config/catalog.php` holds the currency and pagination defaults. A non-destructive migration changed `offers` / `orders` currency defaults from USD to EUR.
- Cart quantity is always 1 per offer and the `PATCH /cart/items/{offer}` route was **removed**.
- Filter options come from `GET /products/filters`; `relevance` sort aliases `popularity` on the catalog endpoint.
- Search (`GET /search`): pg_trgm similarity > 0.3 OR case-insensitive substring on PostgreSQL; SQLite tests use a LIKE fallback. Reuses `ProductResource` and catalog pricing; returns `meta.query` and `meta.result_count`.
- Checkout (later): no order row at all on payment failure; create the order before order items; re-check key status after `lockForUpdate()`.
- Auth: Sanctum personal access tokens named `api`, returned once as `{token, token_type: "Bearer"}`. Wrong email/password uses a distinct `INVALID_CREDENTIALS` (401) code; missing token uses `UNAUTHENTICATED` (401). Duplicate email is a `VALIDATION_ERROR`. `logout` deletes only the current token. Emails are lowercased on input.

## Verified commands (PowerShell)

- `& 'C:\php\php.exe' artisan --version` → `Laravel Framework 13.43.0`
- `& 'C:\php\php.exe' artisan test` → 34 passed, 237 assertions (2 defaults + 15 catalog/product + 7 search + 10 auth tests)
- `& 'C:\php\php.exe' artisan route:list --path=api` → 8 routes (3 catalog + 1 search + 4 auth)
- `& 'C:\php\php.exe' vendor/bin/pint --test` → passed
- `& 'C:\php\php.exe' artisan migrate:status` → all migrations Ran; `artisan db:show` → PostgreSQL 18.6 / `eneba_store`
- `& 'C:\php\php.exe' artisan install:api --no-interaction` → Sanctum v4.5.0 installed and its migration ran
- `& 'C:\php\php.exe' artisan migrate --force` → 10 domain migrations applied; `pg_trgm` extension and `products_name_trgm_idx` verified present in PostgreSQL
- `2026_11_24_150000_change_currency_defaults_to_eur` applied with `artisan migrate --force` (non-destructive default change; no data reset)
- `& 'C:\php\php.exe' artisan migrate:fresh --seed --force --no-interaction` → succeeds on PostgreSQL; `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; & 'C:\php\php.exe' artisan migrate:fresh --force` → succeeds on SQLite
- `$env:PATH = 'C:\php;' + $env:PATH; & 'C:\ProgramData\ComposerSetup\bin\composer.bat' check-platform-reqs` → all requirements satisfied
- `& 'C:\php\php.exe' artisan serve` and `npm run dev` in `frontend/` — both confirmed working by the user.
- In `frontend/`: `npm install`, `npm run typecheck`, `npm test`, `npm run lint`, `npm run format:check`, `npm run build` → all pass (Vitest 5.1.1, 1 test; Vite 8.3.8).

## Hard constraints (from `SPEC.md`)

- No hardcoded frontend data. All content comes from backend endpoints; prices and totals are always computed by the backend, never in the client.
- No real payment processing (simulated or provider test mode, with explicit success/failure outcomes) and no AI integration.
- Single PHP backend — the spec's "each block has its own service/data source" means independently configurable backend endpoints, not microservices (explicit non-goal).

## Acceptance flow

Homepage/Search → Product page + seller offers → Add offer to cart → Checkout (sign-in required) → Simulated payment → Order + digital key → Order history. Eneba styling everywhere.

## Easy-to-miss requirements

- Catalog: active products only; filters for platform / region / price range; sorting by price ascending/descending, popularity, relevance; pagination with page number, page size, metadata, and applied filters echoed back.
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
