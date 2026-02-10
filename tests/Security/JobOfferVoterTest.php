<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Security\Voter\JobOfferVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class JobOfferVoterTest extends TestCase
{
    private JobOfferVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new JobOfferVoter();
    }

    public function testAdminCanEditAnyOffer(): void
    {
        $admin = new User();
        $admin->setRole('ADMIN');

        $offer = new JobOffer();
        $offer->setPartner(new User()); // different user

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testPartnerCanEditOwnOffer(): void
    {
        $partner = new User();
        $partner->setRole('BUSINESS_PARTNER');

        $offer = new JobOffer();
        $offer->setPartner($partner);

        $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testPartnerCannotEditOtherOffer(): void
    {
        $partner = new User();
        $partner->setRole('BUSINESS_PARTNER');

        $otherPartner = new User();
        $otherPartner->setRole('BUSINESS_PARTNER');

        $offer = new JobOffer();
        $offer->setPartner($otherPartner);

        $token = new UsernamePasswordToken($partner, 'main', $partner->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }

    public function testStudentCannotEditOffer(): void
    {
        $student = new User();
        $student->setRole('STUDENT');

        $offer = new JobOffer();
        $offer->setPartner(new User());

        $token = new UsernamePasswordToken($student, 'main', $student->getRoles());

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $offer, [JobOfferVoter::EDIT])
        );
    }
}
