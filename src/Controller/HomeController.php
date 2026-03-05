<?php

namespace App\Controller;

use App\Repository\ClasseRepository;
use App\Repository\ProgramRepository;
use App\Repository\ReclamationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(UserRepository $userRepository, ProgramRepository $programRepository, ClasseRepository $classeRepository, ReclamationRepository $reclamationRepository): Response
    {
        // If user is logged in, redirect to their dashboard
        if ($this->getUser()) {
            $vars = [];
            if ($this->isGranted('ROLE_ADMIN')) {
                $vars = [
                    'userCount'        => $userRepository->count([]),
                    'programCount'     => $programRepository->count([]),
                    'classeCount'      => $classeRepository->count([]),
                    'reclamationCount' => $reclamationRepository->count([]),
                ];
            }
            return $this->render('home/index.html.twig', $vars);
        }

        // Show vitrine landing page for guests
        return $this->render('vitrine/index.html.twig');
    }
}
