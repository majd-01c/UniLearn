<?php

namespace App\Controller\Student;

use App\Entity\CourseDocument;
use App\Entity\User;
use App\Repository\CourseDocumentRepository;
use App\Repository\StudentClasseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Handler\DownloadHandler;

#[Route('/student/documents')]
#[IsGranted('ROLE_STUDENT')]
class StudentDocumentController extends AbstractController
{
    public function __construct(
        private CourseDocumentRepository $documentRepository,
        private StudentClasseRepository $studentClasseRepository,
        private DownloadHandler $downloadHandler
    ) {}

    #[Route('', name: 'app_student_documents')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Get all classes the student is enrolled in
        $studentClasses = $this->studentClasseRepository->findByStudent($user);
        
        // Get all documents for those classes
        $documents = [];
        foreach ($studentClasses as $studentClasse) {
            $classeDocuments = $this->documentRepository->findByClasse($studentClasse->getClasse());
            foreach ($classeDocuments as $doc) {
                $documents[] = $doc;
            }
        }

        // Sort by created date, newest first
        usort($documents, function($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return $this->render('Gestion_Evaluation/student_document/index.html.twig', [
            'documents' => $documents,
            'studentClasses' => $studentClasses,
        ]);
    }

    #[Route('/{id}/download', name: 'app_student_document_download', requirements: ['id' => '\d+'])]
    public function download(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $document = $this->documentRepository->find($id);
        
        if (!$document || !$document->isActive()) {
            $this->addFlash('error', 'Document not found.');
            return $this->redirectToRoute('app_student_documents');
        }

        // Check if student is enrolled in the class
        $classe = $document->getClasse();
        $isEnrolled = $this->studentClasseRepository->isStudentEnrolled($user, $classe);
        
        if (!$isEnrolled) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_documents');
        }

        // Download the file
        return $this->downloadHandler->downloadObject(
            $document,
            'documentFile',
            null,
            $document->getOriginalFileName(),
            false
        );
    }
}
