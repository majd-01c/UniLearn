<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobOfferController extends AbstractController
{
    #[Route('/job-offer', name: 'app_job_offer')]
    public function index(): Response
    {
        return $this->render('job_offer/index.html.twig');
    }
}
