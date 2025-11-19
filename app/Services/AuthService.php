<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Register a new user
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        // Assign customer role by default
        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        } else {
            $user->assignRole('customer');
        }

        return $user;
    }

    /**
     * Authenticate user and return JWT token
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        $credentials = ['email' => $email, 'password' => $password];

        if (!$token = JWTAuth::attempt($credentials)) {
            return null;
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get currently authenticated user
     *
     * @return User|null
     */
    public function me(): ?User
    {
        return JWTAuth::parseToken()->authenticate();
    }

    /**
     * Logout user (invalidate token)
     *
     * @return bool
     */
    public function logout(): bool
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return true;
    }

    /**
     * Refresh JWT token
     *
     * @return array
     */
    public function refresh(): array
    {
        return $this->respondWithToken(JWTAuth::refresh());
    }

    /**
     * Respond with JWT token
     *
     * @param string $token
     * @return array
     */
    protected function respondWithToken(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ];
    }
}
