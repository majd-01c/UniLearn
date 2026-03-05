<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Blocks access to all protected routes when the user still needs
 * to complete the Face ID verification step (2FA gate).
 *
 * Allowed routes during pending verification:
 *   - face_verify_page, face_verify, face_verify_skip
 *   - app_logout
 *   - profiler / wdt (dev toolbar)
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class FaceVerificationListener
{
    private const ALLOWED_ROUTES = [
        'face_verify_page',
        'face_verify',
        'face_verify_skip',
        'app_logout',
        '_wdt',
        '_profiler',
        '_profiler_search',
        '_profiler_search_results',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Only gate if there's a pending face verification flag
        if (!$session->get('needs_face_verification')) {
            return;
        }

        // Must have a logged-in user
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        // Allow certain routes through
        $route = $request->attributes->get('_route');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        // Allow static assets (css, js, images, face-api models)
        $pathInfo = $request->getPathInfo();
        if (preg_match('#^/(_(profiler|wdt)|css|images|js|face-api|bundles|uploads|assets|build)#', $pathInfo)) {
            return;
        }

        // Redirect to face verification page
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('face_verify_page')));
    }
}
