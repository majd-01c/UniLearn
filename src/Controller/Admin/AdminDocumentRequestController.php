<?php

namespace App\Controller\Admin;

use App\Entity\DocumentRequest;
use App\Repository\DocumentRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

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

    #[Route('/{id}', name: 'app_admin_document_request_show', requirements: ['id' => '\d+'])]
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

    #[Route('/{id}/upload', name: 'app_admin_document_request_upload', methods: ['POST'])]
    public function uploadDocument(
        Request $request,
        DocumentRequest $documentRequest,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $uploadedFile = $request->files->get('document_file');

        if (!$uploadedFile) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier.');
            return $this->redirectToRoute('app_admin_document_request_show', ['id' => $documentRequest->getId()]);
        }

        // Validate file type (PDF/DOC/etc.)
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];

        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Type de fichier non autorisé. Formats acceptés: PDF, DOC, DOCX, JPG, PNG');
            return $this->redirectToRoute('app_admin_document_request_show', ['id' => $documentRequest->getId()]);
        }

        // Generate unique filename
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

        // Create upload directory if it doesn't exist
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/student_documents';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        try {
            $uploadedFile->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors du téléchargement du fichier.');
            return $this->redirectToRoute('app_admin_document_request_show', ['id' => $documentRequest->getId()]);
        }

        // Update document request with file path
        $documentRequest->setDocumentPath($newFilename);
        $documentRequest->setStatus('ready');
        $entityManager->flush();

        $this->addFlash('success', 'Document téléchargé avec succès. Le statut a été mis à jour vers "Prête".');

        return $this->redirectToRoute('app_admin_document_request_show', ['id' => $documentRequest->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_document_request_delete', methods: ['POST'])]
    public function delete(
        DocumentRequest $documentRequest,
        EntityManagerInterface $entityManager
    ): Response {
        // Delete file if exists
        if ($documentRequest->getDocumentPath()) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/student_documents/' . $documentRequest->getDocumentPath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $entityManager->remove($documentRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande supprimée avec succès.');

        return $this->redirectToRoute('app_admin_document_requests');
    }
}
