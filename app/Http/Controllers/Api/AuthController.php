<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer for authentication.
 *
 * WHY: FormRequests validate, AuthService applies the rules, and this class
 * only maps the result to the shared JSON envelope — the same split used by
 * the catalog controller.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CartService $cart,
    ) {}

    /**
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());
        $this->cart->mergeGuest($result['user'], $request->validated('cart_token'));

        return ApiResponse::success($this->tokenPayload($result), status: 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated());
        $this->cart->mergeGuest($result['user'], $request->validated('cart_token'));

        return ApiResponse::success($this->tokenPayload($result));
    }

    /**
     * POST /api/auth/logout — guarded by auth:sanctum in routes/api.php.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->auth->logout($user);

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me — guarded by auth:sanctum in routes/api.php.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * @param  array{user: User, token: string}  $result
     * @return array<string, mixed>
     */
    private function tokenPayload(array $result): array
    {
        return [
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            // WHY: clients should send `Authorization: Bearer <token>`; this
            // field removes any guesswork about the expected scheme.
            'token_type' => 'Bearer',
        ];
    }
}
