Goals: Creating a website where users can buy video game keys:
    Store: A catalog where visitors can browse every product in the marketplace. They can
        narrow the list with filters (for example platform, region or price), change the order, and
        search by name.
    Homepage: The landing page is assembled from several different blocks, such as a
        banner, best sellers or deals. Each block gets its content from its own backend service or
        database, so the frontend only displays what it receives and nothing is hardcoded there.
    Search: Visitors can search for products by name from anywhere in the app and see the
        matching results. Search should cope with typos and partial names where it can.
        Product page: The page for a single product, showing its details and the offers from
        several sellers, so a buyer can compare and choose.
    Checkout: A buyer can put products in a cart, pay, and receive the digital key they
        bought. Payment can be simulated or use a payment provider’s test mode. No real
        money is involved.
    Authentication: Visitors can create an account and sign in. Some parts of the app, such
        as checkout and order history, are only for signed-in users.
    Eneba styling: The app should look and feel like Eneba. Colors, typography, spacing and
        components follow the design reference.

    No hardcoded data in frontend
    
    Full-Flow: 1: Homepage/Search -> 2: Product page, Seller offers -> 3: Adding to card -> 4: Checkout -> (Sign in required) -> 5: Payment -> 6: Order and key -> 7: Order history(Final) (Eneba styles everywhere)

Non-Goals: 
    No real payment processing.
    No AI integration.
    No seller registration/dashboard.
    No admin dashboard (seed data is sufficient).
    No social login.
    No email verification.
    No refunds or disputes.
    No native mobile application.
    No microservices architecture.

Inputs and examples:
    1: Filter: Platform = Steam, Region = Europe, Price_Range = 0 - 10$ 
    2: Search: Minecraft/Minec/mincraft(Maybe auto-completation and fix, not in mvp)
    3: Register/Log-in: Email, password
    4: Card operations: Required card operations
    5. Product offer selection:
    - Product ID
    - Offer ID
    6. Cart:
    - Add offer
    - Remove offer
    - Update quantity (if supported)

    7. Checkout:
    - Cart items
    - Simulated payment method
    - Payment success/failure simulation

    8. Sorting:
    - Price ascending/descending
    - Popularity
    - Relevance

    9. Pagination:
    - Page number
    - Page size

Outputs and examples: 
    1. Homepage:
    - Banner data
    - Best sellers
    - Deals

    2. Store:
    - Product catalog
    - Pagination metadata
    - Applied filters

    3. Search:
    - Matching products
    - Search result count

    4. Product:
    - Product details
    - Available seller offers
    - Offer prices and regions

    5. Authentication:
    - User information
    - Authentication status

    6. Cart:
    - Cart items
    - Individual prices
    - Total price

    7. Checkout:
    - Payment status
    - Order ID
    - Order summary

    8. Digital delivery:
    - Purchased digital key
    - Order details

    9. Order history:
    - Previous orders
    - Purchased products
    - Purchase dates

    10. Errors:
        - Validation errors
        - Authentication errors
        - Product/offer not found
        - Payment failure
        - Insufficient inventory

Business Rules:
    1. Only active products are visible in the catalog.
    2. Every product can have multiple seller offers.
    3. Every offer belongs to one product and one seller.
    4. Each offer has its own price, region and available digital keys.
    5. Users can only purchase available offers.
    6. Prices and totals must always be calculated by the backend.
    7. Checkout requires authentication.
    8. Failed payments must not create completed orders or deliver keys.
    9. Successful payments must create an order and assign available digital keys.
    10. A digital key must never be assigned to multiple orders.
    11. Users can only access their own cart and order history.
    12. Homepage content must be retrieved from backend endpoints.
    13. Homepage blocks must be independently configurable.

Tech Stack: 
    Frontend: React + TypeScript
    Backend: PHP + Laravel
    Database: PostgreSQL
    AI-agent: Opencode Big Pickle

Acceptance-Criteria:
    Full-Flow explained above in goals section works properly