<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProgrammeController extends AbstractController
{
    #[Route('/programme', name: 'app_programme')]
    public function index(): Response
    {
        return $this->render('programme/index.html.twig');
    }

    #[Route('/programme/modules', name: 'app_programme_modules')]
    public function modules(): Response
    {
        return $this->render('programme/modules.html.twig');
    }

    #[Route('/programme/courses', name: 'app_programme_courses')]
    public function courses(): Response
    {
        return $this->render('programme/courses.html.twig');
    }

    #[Route('/programme/contenus', name: 'app_programme_contenus')]
    public function contenus(): Response
    {
        return $this->render('programme/contenus.html.twig');
    }
}
