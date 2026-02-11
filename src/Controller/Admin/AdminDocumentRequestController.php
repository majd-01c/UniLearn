<?php

namespace App\Controller\Admin;

use App\Entity\DocumentRequest;
use App\Repository\DocumentRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document-requests')]
#[IsGranted('ROLE_ADMIN')]
class AdminDocumentRequestController extends AbstractController
{
    #[Route('/', name: 'app_admin_document_requests')]
    public function index(DocumentRequestRepository $documentRequestRepository): Response
    {
        $documentRequests = $documentRequestRepository->createQueryBuilder('d')
            ->orderBy('d.status', 'ASC')
            ->addOrderBy('d.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('gestion_user/admin/document_request/index.html.twig', [
            'documentRequests' => $documentRequests,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_document_request_show')]
    public function show(DocumentRequest $documentRequest): Response
    {
        return $this->render('gestion_user/admin/document_request/show.html.twig', [
            'documentRequest' => $documentRequest,
        ]);
    }

    #[Route('/{id}/update-status', name: 'app_admin_document_request_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        DocumentRequest $documentRequest,
        EntityManagerInterface $entityManager
    ): Response {
        $status = $request->request->get('status');

        if ($status) {
            $documentRequest->setStatus($status);
            
            if ($status === 'delivered') {
                $documentRequest->setDeliveredAt(new \DateTime());
            }
        }

        $entityManager->flush();

        $this->addFlash('success', 'Statut mis à jour avec succès.');

        return $this->redirectToRoute('app_admin_document_request_show', ['id' => $documentRequest->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_document_request_delete', methods: ['POST'])]
    public function delete(
        DocumentRequest $documentRequest,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($documentRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande supprimée avec succès.');

        return $this->redirectToRoute('app_admin_document_requests');
    }
}
