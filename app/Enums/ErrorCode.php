<?php

namespace App\Enums;

/**
 * Stable, machine-readable error codes returned by every /api endpoint.
 *
 * The SPA must branch on `code`, never on the human-readable `message`.
 * HTTP status codes live here too so exception rendering has one source of truth.
 */
enum ErrorCode: string
{
    case ValidationError = 'VALIDATION_ERROR';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case ProductNotFound = 'PRODUCT_NOT_FOUND';
    case OfferNotFound = 'OFFER_NOT_FOUND';
    case PaymentFailed = 'PAYMENT_FAILED';
    case InsufficientInventory = 'INSUFFICIENT_INVENTORY';
    case InternalError = 'INTERNAL_ERROR';

    /**
     * Map each business error to an HTTP status.
     *
     * WHY: 402 intentionally signals a simulated payment decline, while
     * 409 signals a stock conflict. Keeping this mapping in the enum prevents
     * controllers and the exception handler from drifting apart.
     */
    public function status(): int
    {
        return match ($this) {
            self::ValidationError => 422,
            self::Unauthenticated => 401,
            self::Forbidden => 403,
            self::NotFound, self::ProductNotFound, self::OfferNotFound => 404,
            self::PaymentFailed => 402,
            self::InsufficientInventory => 409,
            self::InternalError => 500,
        };
    }

    /**
     * A safe default message for callers that map framework exceptions.
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::ValidationError => 'The given parameters were invalid.',
            self::Unauthenticated => 'Authentication is required.',
            self::Forbidden => 'You are not allowed to perform this action.',
            self::NotFound => 'The requested resource was not found.',
            self::ProductNotFound => 'Product not found.',
            self::OfferNotFound => 'Offer not found.',
            self::PaymentFailed => 'Payment failed.',
            self::InsufficientInventory => 'Insufficient inventory.',
            self::InternalError => 'An unexpected error occurred.',
        };
    }
}
