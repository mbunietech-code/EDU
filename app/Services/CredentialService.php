<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class CredentialService
{
    public function encrypt(string $credentials): string
    {
        return Crypt::encryptString($credentials);
    }

    public function decrypt(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    public function decryptValue(?string $encrypted): ?string
    {
        if ($encrypted === null) {
            return null;
        }

        try {
            return $this->decrypt($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }
}