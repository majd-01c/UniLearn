<?php

namespace App\Controller\Admin;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reclamations')]
#[IsGranted('ROLE_ADMIN')]
class AdminReclamationController extends AbstractController
{
    #[Route('/', name: 'app_admin_reclamations')]
    public function index(ReclamationRepository $reclamationRepository): Response
    {
        $reclamations = $reclamationRepository->createQueryBuilder('r')
            ->orderBy('r.status', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('gestion_user/admin/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_reclamation_show')]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('gestion_user/admin/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/respond', name: 'app_admin_reclamation_respond', methods: ['POST'])]
    public function respond(
        Request $request,
        Reclamation $reclamation,
        EntityManagerInterface $entityManager
    ): Response {
        $response = $request->request->get('admin_response');
        $status = $request->request->get('status');

        if ($response) {
            $reclamation->setAdminResponse($response);
        }

        if ($status) {
            $reclamation->setStatus($status);
            
            if ($status === 'resolved') {
                $reclamation->setResolvedAt(new \DateTime());
            }
        }

        $entityManager->flush();

        $this->addFlash('success', 'Réclamation mise à jour avec succès.');

        return $this->redirectToRoute('app_admin_reclamation_show', ['id' => $reclamation->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_reclamation_delete', methods: ['POST'])]
    public function delete(
        Reclamation $reclamation,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($reclamation);
        $entityManager->flush();

        $this->addFlash('success', 'Réclamation supprimée avec succès.');

        return $this->redirectToRoute('app_admin_reclamations');
    }
}
