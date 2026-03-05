<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];

    #[Route('/switch-locale/{locale}', name: 'app_switch_locale', requirements: ['locale' => 'en|fr|ar'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'en';
        }

        $request->getSession()->set('_locale', $locale);

        // Check for explicit redirect_uri parameter first
        $redirectUri = $request->query->get('redirect_uri');
        if ($redirectUri && $this->isValidRedirectUri($redirectUri)) {
            return $this->redirect($redirectUri);
        }

        // Redirect back to the referring page, or home
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * Check if the redirect URI is valid (same domain)
     */
    private function isValidRedirectUri(string $uri): bool
    {
        // Only allow relative URLs or URLs on the same domain
        if (str_starts_with($uri, '/')) {
            return true; // Relative URL is safe
        }

        $requestUri = $this->getRequest()->getSchemeAndHttpHost();
        return str_starts_with($uri, $requestUri);
    }

    /**
     * Get the current request from the container
     */
    private function getRequest(): Request
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }
}
