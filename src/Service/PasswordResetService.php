<?php

namespace App\Service;

use App\Entity\ResetToken;
use App\Entity\User;
use App\Repository\ResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for handling password reset operations
 */
class PasswordResetService
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_VALIDITY_HOURS = 2;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetTokenRepository $resetTokenRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Generate a new reset token for a user
     */
    public function generateResetToken(User $user): ResetToken
    {
        // Invalidate any existing unused tokens for this user
        $existingTokens = $this->resetTokenRepository->findBy([
            'user' => $user,
            'used' => false,
        ]);

        foreach ($existingTokens as $token) {
            $token->setUsed(true);
        }

        // Create new reset token
        $resetToken = new ResetToken();
        $resetToken->setToken($this->generateSecureToken());
        $resetToken->setUser($user);
        $resetToken->setExpiryDate(
            (new \DateTime())->add(new \DateInterval('PT' . self::TOKEN_VALIDITY_HOURS . 'H'))
        );

        $this->entityManager->persist($resetToken);
        $this->entityManager->flush();

        return $resetToken;
    }

    /**
     * Validate a reset token
     */
    public function validateResetToken(string $token): ?ResetToken
    {
        $resetToken = $this->resetTokenRepository->findOneBy([
            'token' => $token,
            'used' => false,
        ]);

        if (!$resetToken) {
            return null;
        }

        // Check if token has expired
        if ($resetToken->getExpiryDate() < new \DateTime()) {
            return null;
        }

        return $resetToken;
    }

    /**
     * Reset user password with a valid reset token
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        try {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setMustChangePassword(false);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark a reset token as used after successful password reset
     */
    public function markTokenAsUsed(ResetToken $resetToken): void
    {
        $resetToken->setUsed(true);
        $this->entityManager->flush();
    }

    /**
     * Generate a secure random token
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Get token validity hours
     */
    public static function getTokenValidityHours(): int
    {
        return self::TOKEN_VALIDITY_HOURS;
    }
}
