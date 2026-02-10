<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\JobOffer;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class JobOfferVoter extends Voter
{
    public const EDIT   = 'JOB_OFFER_EDIT';
    public const DELETE = 'JOB_OFFER_DELETE';
    public const CLOSE  = 'JOB_OFFER_CLOSE';
    public const REOPEN = 'JOB_OFFER_REOPEN';
    public const VIEW_APPLICATIONS = 'JOB_OFFER_VIEW_APPLICATIONS';

    private const ATTRIBUTES = [
        self::EDIT,
        self::DELETE,
        self::CLOSE,
        self::REOPEN,
        self::VIEW_APPLICATIONS,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof JobOffer;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var JobOffer $offer */
        $offer = $subject;

        // Admin can do everything on any offer
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Partner can manage only their own offers
        if (in_array('ROLE_BUSINESS_PARTNER', $user->getRoles(), true)) {
            return $offer->getPartner() === $user;
        }

        return false;
    }
}
