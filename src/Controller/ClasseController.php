<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClasseController extends AbstractController
{
    #[Route('/classe', name: 'app_classe')]
    public function index(): Response
    {
        return $this->render('Gestion_Program/classe/index.html.twig');
    }
}
