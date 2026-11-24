<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read model for the signed-in user's order history.
 *
 * Invariant owned by this class: every lookup is scoped by the authenticated
 * user's id. Another user's order is indistinguishable from a missing one, so
 * the endpoint cannot be used to enumerate orders.
 */
class OrderService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        // WHY: items are loaded so the history shows purchased products.
        // digitalKey is deliberately not loaded here — key codes belong in
        // the detail response and checkout response.
        return Order::query()
            ->where('orders.user_id', $user->id)
            ->with('items.offer.product')
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->paginate(
                (int) ($filters['per_page'] ?? config('catalog.per_page')),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            );
    }

    /**
     * @throws ApiException when the order does not belong to this user
     */
    public function findForUser(User $user, int $orderId): Order
    {
        $order = Order::query()
            ->where('orders.user_id', $user->id)
            ->where('orders.id', $orderId)
            ->with(['items.offer.product', 'items.digitalKey'])
            ->first();

        if ($order === null) {
            // WHY: 404 rather than 403 so the existence of another user's
            // order is never confirmed.
            throw ApiException::notFound();
        }

        return $order;
    }
}
