# AGENTS.md

## Repo state

- Greenfield: only `SPEC.md` exists — no code, config, lockfiles, or commits. `SPEC.md` is the only source of truth; update this file when scripts/config land.
- No build, test, lint, or dev commands exist yet. Record exact commands here (including single-test invocation and any required order) once tooling is added.

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

- Frontend: React + TypeScript
- Backend: PHP + Laravel
- Database: PostgreSQL
