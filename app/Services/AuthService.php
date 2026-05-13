<?php

namespace App\Services;

class AuthService
{
    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function generateSalt(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function hashPassword(string $password, string $salt): string
    {
        return hash('sha256', $salt.':'.$password);
    }

    public function verifyPassword(string $password, string $salt, string $passwordHash): bool
    {
        return hash_equals($passwordHash, $this->hashPassword($password, $salt));
    }

    public function generateAuthToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
