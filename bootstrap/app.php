<?php

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Every renderer is guarded by $request->is('api/*') so Blade/web
         * error pages keep Laravel's normal behaviour.
         */
        $exceptions->render(function (ApiException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Business exceptions already carry code, message, errors and status.
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->errors, $e->status);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // WHY: some validation can be triggered outside a FormRequest.
            return ApiResponse::error(
                ErrorCode::ValidationError,
                ErrorCode::ValidationError->defaultMessage(),
                $e->errors()->toArray(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::Unauthenticated, ErrorCode::Unauthenticated->defaultMessage());
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::Forbidden, ErrorCode::Forbidden->defaultMessage());
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(ErrorCode::NotFound, ErrorCode::NotFound->defaultMessage());
        });
    })->create();
