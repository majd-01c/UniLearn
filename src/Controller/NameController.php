<?php

namespace App\Controller;

use App\Entity\Name;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NameController extends AbstractController
{
    #[Route('/name', name: 'app_name')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $savedName = null;

        if ($request->isMethod('POST')) {
            $nameValue = $request->request->get('name');
            
            if ($nameValue) {
                $name = new Name();
                $name->setName($nameValue);
                
                $entityManager->persist($name);
                $entityManager->flush();
                
                $savedName = $nameValue;
            }
        }

        // Get all names from database
        $names = $entityManager->getRepository(Name::class)->findAll();

        return $this->render('Gestion_user/name/index.html.twig', [
            'names' => $names,
            'savedName' => $savedName,
        ]);
    }
}
