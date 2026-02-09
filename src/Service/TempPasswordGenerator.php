<?php

namespace App\Service;

/**
 * Service for generating temporary passwords
 */
class TempPasswordGenerator
{
    /**
     * Generate a secure temporary password
     *
     * @param int $length Password length (default: 12)
     * @return string Generated password
     */
    public function generate(int $length = 12): string
    {
        // Characters to use in password (avoiding ambiguous characters like 0, O, l, 1)
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '23456789';
        $special = '!@#$%&*';

        $allChars = $lowercase . $uppercase . $numbers . $special;

        // Ensure at least one character from each category
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle to avoid predictable patterns
        return str_shuffle($password);
    }
}
