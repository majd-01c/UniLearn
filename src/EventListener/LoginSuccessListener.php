<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * After a successful email+password login, if the user has Face ID enabled,
 * set a session flag so that the FaceVerificationListener gates all routes
 * until the user passes face verification.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        if (!$user->isFaceEnabled()) {
            return; // Normal login — no extra step
        }

        // Face ID is active → mark session so the gate redirects to /face-verify
        $request = $event->getRequest();
        $session = $request->getSession();
        $session->set('needs_face_verification', true);
        $session->set('face_verify_attempts', 0);

        // Override the response to redirect to the face verify page
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('face_verify_page')));
    }
}
