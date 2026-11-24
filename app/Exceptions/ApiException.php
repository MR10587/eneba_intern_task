<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;

/**
 * Application exception carrying a stable error code for the JSON API.
 *
 * WHY: Laravel's default exceptions only carry a message. The SPA needs a
 * reliable `code` to decide what to show, so every business failure is thrown
 * as this exception and rendered centrally in bootstrap/app.php.
 *
 * Note: we deliberately name the promoted property $errorCode instead of
 * $code, because Exception already declares a protected integer $code.
 */
class ApiException extends Exception
{
    public readonly int $status;

    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $errors = [],
        ?int $status = null,
    ) {
        parent::__construct($message);

        // WHY: derive the HTTP status from the code unless explicitly
        // overridden, so the two can never silently disagree.
        $this->status = $status ?? $errorCode->status();
    }

    /**
     * Validation failures include a field => messages map.
     */
    public static function validation(array $errors): self
    {
        return new self(
            ErrorCode::ValidationError,
            ErrorCode::ValidationError->defaultMessage(),
            $errors,
            ErrorCode::ValidationError->status(),
        );
    }

    /**
     * Wrong email/password combination on /auth/login.
     *
     * WHY: the same code is used for an unknown email and a wrong password so
     * the endpoint cannot be used to enumerate registered accounts.
     */
    public static function invalidCredentials(): self
    {
        return new self(
            ErrorCode::InvalidCredentials,
            ErrorCode::InvalidCredentials->defaultMessage(),
            [],
            ErrorCode::InvalidCredentials->status(),
        );
    }

    public static function productNotFound(): self
    {
        return new self(
            ErrorCode::ProductNotFound,
            ErrorCode::ProductNotFound->defaultMessage(),
            [],
            ErrorCode::ProductNotFound->status(),
        );
    }

    public static function offerNotFound(): self
    {
        return new self(
            ErrorCode::OfferNotFound,
            ErrorCode::OfferNotFound->defaultMessage(),
            [],
            ErrorCode::OfferNotFound->status(),
        );
    }

    public static function insufficientInventory(): self
    {
        return new self(
            ErrorCode::InsufficientInventory,
            ErrorCode::InsufficientInventory->defaultMessage(),
            [],
            ErrorCode::InsufficientInventory->status(),
        );
    }

    public static function paymentFailed(): self
    {
        return new self(
            ErrorCode::PaymentFailed,
            ErrorCode::PaymentFailed->defaultMessage(),
            [],
            ErrorCode::PaymentFailed->status(),
        );
    }

    public static function notFound(): self
    {
        return new self(
            ErrorCode::NotFound,
            ErrorCode::NotFound->defaultMessage(),
            [],
            ErrorCode::NotFound->status(),
        );
    }
}
