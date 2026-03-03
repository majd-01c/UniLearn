<?php

namespace App\Controller\Teacher;

use App\Entity\CourseDocument;
use App\Entity\User;
use App\Repository\CourseDocumentRepository;
use App\Repository\TeacherClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Handler\DownloadHandler;

#[Route('/teacher/documents')]
#[IsGranted('ROLE_TEACHER')]
class TeacherDocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourseDocumentRepository $documentRepository,
        private TeacherClasseRepository $teacherClasseRepository,
        private DownloadHandler $downloadHandler
    ) {}

    #[Route('', name: 'app_teacher_documents')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Get all classes the teacher is assigned to
        $teacherClasses = $this->teacherClasseRepository->findByTeacher($user);
        
        // Get all documents uploaded by this teacher
        $documents = $this->documentRepository->findBy(
            ['uploadedBy' => $user, 'isActive' => true],
            ['createdAt' => 'DESC']
        );

        return $this->render('Gestion_Program/teacher_document/index.html.twig', [
            'documents' => $documents,
            'teacherClasses' => $teacherClasses,
        ]);
    }

    #[Route('/upload', name: 'app_teacher_document_upload')]
    public function upload(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Get all classes the teacher is assigned to
        $teacherClasses = $this->teacherClasseRepository->findByTeacher($user);

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $classeId = $request->request->get('classe_id');

            if (!$title || !$classeId) {
                $this->addFlash('error', 'Title and class are required.');
                return $this->redirectToRoute('app_teacher_document_upload');
            }

            // Verify teacher is assigned to this class
            $teacherClasse = null;
            foreach ($teacherClasses as $tc) {
                if ($tc->getClasse()->getId() == $classeId) {
                    $teacherClasse = $tc;
                    break;
                }
            }

            if (!$teacherClasse) {
                $this->addFlash('error', 'You are not assigned to this class.');
                return $this->redirectToRoute('app_teacher_document_upload');
            }

            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('document_file');
            
            if (!$uploadedFile) {
                $this->addFlash('error', 'Please upload a file.');
                return $this->redirectToRoute('app_teacher_document_upload');
            }

            $document = new CourseDocument();
            $document->setTitle($title);
            $document->setDescription($description);
            $document->setClasse($teacherClasse->getClasse());
            $document->setUploadedBy($user);
            $document->setDocumentFile($uploadedFile);

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Document uploaded successfully. Students can now download it.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        return $this->render('Gestion_Program/teacher_document/upload.html.twig', [
            'teacherClasses' => $teacherClasses,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_teacher_document_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $document = $this->documentRepository->find($id);
        
        if (!$document || $document->getUploadedBy()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Document not found or you are not the owner.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        $teacherClasses = $this->teacherClasseRepository->findByTeacher($user);

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $classeId = $request->request->get('classe_id');

            if (!$title || !$classeId) {
                $this->addFlash('error', 'Title and class are required.');
                return $this->redirectToRoute('app_teacher_document_edit', ['id' => $id]);
            }

            // Verify teacher is assigned to this class
            $teacherClasse = null;
            foreach ($teacherClasses as $tc) {
                if ($tc->getClasse()->getId() == $classeId) {
                    $teacherClasse = $tc;
                    break;
                }
            }

            if (!$teacherClasse) {
                $this->addFlash('error', 'You are not assigned to this class.');
                return $this->redirectToRoute('app_teacher_document_edit', ['id' => $id]);
            }

            $document->setTitle($title);
            $document->setDescription($description);
            $document->setClasse($teacherClasse->getClasse());

            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('document_file');
            if ($uploadedFile) {
                $document->setDocumentFile($uploadedFile);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Document updated successfully.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        return $this->render('Gestion_Program/teacher_document/edit.html.twig', [
            'document' => $document,
            'teacherClasses' => $teacherClasses,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_teacher_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $document = $this->documentRepository->find($id);
        
        if (!$document || $document->getUploadedBy()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Document not found or you are not the owner.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        if (!$this->isCsrfTokenValid('delete_document_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document deleted successfully.');
        return $this->redirectToRoute('app_teacher_documents');
    }

    #[Route('/{id}/download', name: 'app_teacher_document_download', requirements: ['id' => '\d+'])]
    public function download(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $document = $this->documentRepository->find($id);
        
        if (!$document || $document->getUploadedBy()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Document not found.');
            return $this->redirectToRoute('app_teacher_documents');
        }

        return $this->downloadHandler->downloadObject(
            $document,
            'documentFile',
            null,
            $document->getOriginalFileName(),
            false
        );
    }
}
