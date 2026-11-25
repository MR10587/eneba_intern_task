# Eneba SPA

React 19 + TypeScript + Vite frontend for the Eneba digital key marketplace demo. It consumes the Laravel API at `/api`; all prices, totals, availability, and order data are computed by the backend.

## Commands

```powershell
npm install
npm run dev
npm run typecheck
npm test
npm test:watch
npm run lint
npm run format
npm run format:check
npm run build
npm run preview
```

`npm run dev` starts on port `5173`. Requests to `/api/*` are proxied to `http://127.0.0.1:8000`, so the Laravel server must be running for the storefront and all data-driven pages.

## Routes

| Route                 | Purpose                                               | Auth |
| --------------------- | ----------------------------------------------------- | ---- |
| `/`                   | Purple/midnight live storefront and API cart drawer   | No   |
| `/browse`, `/home`    | API-driven `GET /home` blocks                         | No   |
| `/products`           | API catalog with URL-driven filters, sort, pagination | No   |
| `/products/:slug`     | Product detail and seller offers                      | No   |
| `/search?q=`          | Typo-tolerant API search results                      | No   |
| `/login`, `/register` | Email/password auth                                   | No   |
| `/cart`               | Cart with backend-computed totals                     | No   |
| `/checkout`           | Simulated payment and order summary                   | Yes  |
| `/orders/:id`         | Order confirmation and delivered key                  | Yes  |
| `/orders`             | Order history                                         | Yes  |

Guests may browse and fill `/cart`. Checkout and order pages are guarded: unauthenticated users are redirected to `/login` with the intended destination preserved.

## Structure

| Path                       | Purpose                                                                    |
| -------------------------- | -------------------------------------------------------------------------- |
| `src/api/client.ts`        | `/api` fetch wrapper, typed envelopes, coded `ApiError`, token persistence |
| `src/api/endpoints.ts`     | Typed wrappers for every backend endpoint                                  |
| `src/types/api.ts`         | Backend DTOs; `src/types/guards.ts` validates unknown block data           |
| `src/hooks/useAsync.ts`    | Shared loading/error/data/reload state                                     |
| `src/context/`             | `AuthProvider` and `useAuth`                                               |
| `src/components/auth/`     | `RequireAuth` and the shared login/register form                           |
| `src/components/catalog/`  | API product cards, filters, offers, and the live marketplace card          |
| `src/components/checkout/` | Simulated payment form                                                     |
| `src/components/layout/`   | API shell and the purple storefront navbar/footer                          |
| `src/pages/`               | Route components, including the live root storefront                       |
| `src/data/categories.ts`   | UI-only category labels; product data always comes from the API            |
| `src/test/`                | Fixtures, auth-aware test renderer, and jsdom setup                        |

## Auth and checkout

- A successful login or registration stores the Sanctum token in `localStorage` under `eneba_token`.
- Guest carts use an opaque token under `eneba_cart_token`; login/register passes it so saved lines move into the account cart.
- Seed user: `test@example.com` / `password`.
- Any 13–19 digit card succeeds; a card ending in `0002` returns `402 PAYMENT_FAILED`.
- The full card number is sent only to the local API and is never stored; the response shows the masked order reference.
- On success the app navigates to `/orders/:id`, where the delivered digital key is visible.
- If any API cart line is unavailable, `/cart` names it and disables checkout; a direct visit to `/checkout` redirects back to `/cart`.

## Add-to-cart feedback

Every successful add shows an accessible toast (`role="status"`) confirming the item was added and is ready for checkout. The root storefront toast includes a `View cart` action that opens the cart drawer; the product page toast appears while navigating to `/cart`. Guests can add without signing in and are asked to sign in only at checkout. Toasts auto-dismiss after four seconds and can also be dismissed manually.

Selecting Games, Gift Cards, Top-Ups, Hardware, or Best Deals from the top category bar, hero CTAs, or footer links updates the active filter and smooth-scrolls to the marketplace grid. The filter is sent to `/products` or `/search`, so the API applies it before pagination instead of the SPA slicing a page.

## Data rules

No page hardcodes products, prices, blocks, or totals. `/` reads `/home`, `/products`, `/search`, and `/products/filters`; adding to its cart resolves a live offer through `/products/:slug` and posts to `/cart/items`. Category labels and generated cover colors are presentation-only.

Money is always displayed from backend integer cents in EUR via `src/utils/format.ts`; the client does not recalculate prices.

## Tooling

- Vite 8, React 19, TypeScript 7
- Tailwind CSS 4 for the purple storefront, CSS tokens for the API shell
- Lucide React icons
- Vitest + Testing Library for tests
- oxlint for linting, Prettier for formatting

No ESLint: `typescript-eslint@8` cannot support TypeScript 7, while oxlint works with the current toolchain.
