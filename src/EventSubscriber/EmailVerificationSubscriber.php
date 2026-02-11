<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EmailVerificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Skip verification check for these routes
        $allowedRoutes = [
            'app_verify_email',
            'app_verify_resend',
            'app_logout',
            'app_login',
            'app_change_password',
        ];

        if (in_array($route, $allowedRoutes)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Redirect to verification page  if user is not verified
        if (!$user->isVerified() && $user->isNeedsVerification()) {
            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_verify_email'))
            );
        }
    }
}
