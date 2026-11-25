# Eneba — digital game key marketplace demo

A full-stack demo storefront built with a Laravel 13 API and a separate React 19 + TypeScript SPA. The API owns all catalog, pricing, cart, checkout, order, and digital-key logic; the frontend renders that data and never computes prices.

Payments are simulated only. Cards ending in `0002` fail intentionally; no real payment provider is used.

## Layout

| Path                                                 | Purpose                                                              |
| ---------------------------------------------------- | -------------------------------------------------------------------- |
| `app/`, `routes/api.php`, `database/`, `tests/`      | Laravel API, migrations, deterministic seeders, feature tests        |
| `frontend/`                                          | Vite + React SPA on port `5173`, proxying `/api` to `127.0.0.1:8000` |
| `docs/api-contract.md`                               | Authoritative request/response shapes and error codes                |
| `SPEC.md`, `plan.md`, `AGENTS.md`, `CHAT_HISTORY.md` | Product spec, implementation record, agent notes, decisions          |

The root `/` route is the live purple storefront: it reads homepage blocks, products, filters, and search results from the API. Guests can add offers to a server-side cart; signing in is required only at checkout and order history. Purchase pages remain available at `/browse`, `/products`, `/search`, `/cart`, `/checkout`, and `/orders`.

## Requirements

- PHP 8.5+ with `fileinfo`, `intl`, `pdo_pgsql`, `pdo_sqlite`, and `zip`
- Composer 2+
- PostgreSQL 18 (local `.env` uses `eneba_store` on `127.0.0.1:5432`)
- Node 24+ and npm 11+

Docker/Sail is not required. On the verified Windows machine, PHP and Composer are not on `PATH`; use the explicit paths below.

## Setup

```powershell
Copy-Item .env.example .env
& 'C:\php\php.exe' artisan key:generate
& 'C:\php\php.exe' artisan migrate:fresh --seed --force
```

Configure `.env` for PostgreSQL:

```dotenv
APP_NAME=Eneba
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=eneba_store
DB_USERNAME=postgres
DB_PASSWORD=<your local password>
```

Install frontend dependencies:

```powershell
cd frontend
npm install
```

`migrate:fresh --seed` is idempotent by natural keys and creates 39 products, 3 sellers, 98 offers, 401 digital keys, 4 homepage blocks, and the demo user. The catalog covers games, gift cards, top-ups, and hardware; the storefront's category filters are sent to the API and applied before pagination.

## Run locally

Terminal 1 — API:

```powershell
& 'C:\php\php.exe' artisan serve
```

Terminal 2 — SPA:

```powershell
cd frontend
npm run dev
```

Open `http://localhost:5173`. The Vite proxy forwards `/api/*` to `http://127.0.0.1:8000`.

## Demo credentials and test card

- Email: `test@example.com`
- Password: `password`
- Successful payment: any 13–19 digit card number, e.g. `4242 4242 4242 4242`
- Failed payment: any card ending in `0002`, e.g. `4242 4242 4242 0002`

Only the masked last four digits are stored in `payment_reference`. A failed payment creates no order and delivers no key.

## Full-flow checklist

1. Open the live storefront at `/`, browse `/browse`, or search `mincraft` at `/search?q=mincraft`.
2. Open a product, review seller offers, and add an available offer as a guest.
3. Review `/cart`; totals come from the API. An unavailable line is named and disables checkout until removed.
4. Select checkout; sign in or register there. Any guest cart lines follow you into your account.
5. At `/checkout`, first try a card ending in `0002` and confirm the failure message.
6. Retry with a success card; the order detail page shows the delivered key.
7. Open `/orders` and confirm the purchase and date.

## Verification

Backend:

```powershell
& 'C:\php\php.exe' artisan test
& 'C:\php\php.exe' vendor/bin/pint --test
& 'C:\php\php.exe' artisan route:list --path=api
```

To run the PostgreSQL-only typo tests, create an `eneba_store_test` database and run:

```powershell
& 'C:\php\php.exe' vendor/bin/phpunit -c phpunit.pgsql.xml
```

Frontend:

```powershell
cd frontend
npm run typecheck
npm test
npm run lint
npm run format:check
npm run build
```

Current verified results: **77 backend tests / 582 assertions**, Pint passed, **15 API routes**, and **33 frontend tests across 15 files** with typecheck, lint, format, and build all passing.

## Key API endpoints

| Method   | Endpoint                  | Auth |
| -------- | ------------------------- | ---- |
| `GET`    | `/api/home`               | No   |
| `GET`    | `/api/products/filters`   | No   |
| `GET`    | `/api/products`           | No   |
| `GET`    | `/api/products/{slug}`    | No   |
| `GET`    | `/api/search`             | No   |
| `POST`   | `/api/auth/register`      | No   |
| `POST`   | `/api/auth/login`         | No   |
| `POST`   | `/api/auth/logout`        | Yes  |
| `GET`    | `/api/auth/me`            | Yes  |
| `GET`    | `/api/cart`               | No   |
| `POST`   | `/api/cart/items`         | No   |
| `DELETE` | `/api/cart/items/{offer}` | No   |
| `POST`   | `/api/checkout`           | Yes  |
| `GET`    | `/api/orders`             | Yes  |
| `GET`    | `/api/orders/{id}`        | Yes  |

All money values are integer cents in EUR. See `docs/api-contract.md` for full shapes and coded errors.

## Deployment

See `DEPLOYMENT.md` for the Render (Docker) + Neon (PostgreSQL) + Vercel (Vite) setup, required dashboard variables, and verification steps. No secrets are stored in this repository.

## Implementation notes

- Sanctum personal access tokens are stored in the browser under `eneba_token`.
- Guest carts are keyed by an opaque token stored under `eneba_cart_token` and merged into the user cart at sign-in.
- Cart quantity is always one per offer; there is no quantity-update endpoint.
- Checkout rechecks live product, offer, and key state inside a transaction.
- A digital key is assigned to at most one order.
- No page hardcodes product data; `/` and the other storefront routes all read live API data.
- No Eneba logo, artwork, or other proprietary asset files are bundled; the name is used as a text wordmark only.
