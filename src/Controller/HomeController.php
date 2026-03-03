<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // If user is logged in, redirect to their dashboard
        if ($this->getUser()) {
            return $this->render('home/index.html.twig');
        }

        // Show vitrine landing page for guests
        return $this->render('vitrine/index.html.twig');
    }
}
