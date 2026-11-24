<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication rules, independent of HTTP.
 *
 * WHY: token issuance and credential checking are business rules. Keeping
 * them here makes the controller a thin adapter and lets the same logic be
 * reused by later slices (cart/checkout need the authenticated user).
 */
class AuthService
{
    /**
     * Create an account and issue its first API token.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // WHY: raw passwords are never persisted. The model also has a
            // `hashed` cast as a second line of defence, but making it
            // explicit here keeps this security-critical step obvious.
            'password' => Hash::make($data['password']),
        ]);

        return $this->issueToken($user);
    }

    /**
     * Verify credentials and issue a fresh token.
     *
     * @param  array{email: string, password: string}  $data
     * @return array{user: User, token: string}
     *
     * @throws ApiException when the email or password is wrong
     */
    public function login(array $data): array
    {
        $user = User::query()->where('email', $data['email'])->first();

        // WHY: Hash::check on a missing user would be a null dereference, and
        // treating both failures identically prevents account enumeration.
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ApiException::invalidCredentials();
        }

        return $this->issueToken($user);
    }

    /**
     * Revoke only the token used for this request.
     *
     * WHY: a user may be signed in on several devices. Deleting all tokens
     * would sign them out everywhere, which is not the intended behaviour.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        return [
            'user' => $user,
            // Sanctum stores only a hash of the token. The plain text is
            // returned once, here, and is never logged or persisted.
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
