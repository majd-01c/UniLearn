<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Handles post-login actions such as checking if password change is required
 */
class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // Check if user is our User entity and must change password
        if ($user instanceof User && $user->isMustChangePassword()) {
            $response = new RedirectResponse(
                $this->urlGenerator->generate('profile_change_password')
            );
            $event->setResponse($response);
        }

        // Check if user is inactive
        if ($user instanceof User && !$user->isActive()) {
            // This shouldn't happen as security.yaml should block inactive users
            // But just in case, we redirect to login with error
            $response = new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            );
            $event->setResponse($response);
        }
    }
}
