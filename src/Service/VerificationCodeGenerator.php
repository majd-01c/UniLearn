<?php

namespace App\Service;

class VerificationCodeGenerator
{
    /**
     * Generate a 6-digit verification code
     */
    public function generate(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if verification code is still valid (within 1 minute)
     */
    public function isCodeValid(?\DateTimeImmutable $codeExpiryDate): bool
    {
        if ($codeExpiryDate === null) {
            return false;
        }

        $now = new \DateTimeImmutable();
        return $now <= $codeExpiryDate;
    }

    /**
     * Get expiry date (1 minute from now)
     */
    public function getExpiryDate(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify('+1 minute');
    }
}
