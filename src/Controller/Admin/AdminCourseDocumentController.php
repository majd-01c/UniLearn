<?php

namespace App\Controller\Admin;

use App\Entity\CourseDocument;
use App\Entity\User;
use App\Repository\ClasseRepository;
use App\Repository\CourseDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents')]
#[IsGranted('ROLE_ADMIN')]
class AdminCourseDocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourseDocumentRepository $documentRepository,
        private ClasseRepository $classeRepository
    ) {}

    #[Route('', name: 'app_admin_course_documents')]
    public function index(): Response
    {
        $documents = $this->documentRepository->findAllActive();
        $classes = $this->classeRepository->findAll();

        return $this->render('Gestion_Evaluation/admin_document/index.html.twig', [
            'documents' => $documents,
            'classes' => $classes,
        ]);
    }

    #[Route('/create', name: 'app_admin_course_document_create')]
    public function create(Request $request): Response
    {
        $classes = $this->classeRepository->findAll();

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();

            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $classeId = $request->request->get('classe_id');

            if (!$title || !$classeId) {
                $this->addFlash('error', 'Title and class are required.');
                return $this->redirectToRoute('app_admin_course_document_create');
            }

            $classe = $this->classeRepository->find($classeId);
            if (!$classe) {
                $this->addFlash('error', 'Class not found.');
                return $this->redirectToRoute('app_admin_course_document_create');
            }

            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('document_file');
            
            if (!$uploadedFile) {
                $this->addFlash('error', 'Please upload a file.');
                return $this->redirectToRoute('app_admin_course_document_create');
            }

            $document = new CourseDocument();
            $document->setTitle($title);
            $document->setDescription($description);
            $document->setClasse($classe);
            $document->setUploadedBy($user);
            $document->setDocumentFile($uploadedFile);

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Document uploaded successfully.');
            return $this->redirectToRoute('app_admin_course_documents');
        }

        return $this->render('Gestion_Evaluation/admin_document/create.html.twig', [
            'classes' => $classes,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_course_document_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $document = $this->documentRepository->find($id);
        
        if (!$document) {
            $this->addFlash('error', 'Document not found.');
            return $this->redirectToRoute('app_admin_course_documents');
        }

        $classes = $this->classeRepository->findAll();

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $classeId = $request->request->get('classe_id');

            if (!$title || !$classeId) {
                $this->addFlash('error', 'Title and class are required.');
                return $this->redirectToRoute('app_admin_course_document_edit', ['id' => $id]);
            }

            $classe = $this->classeRepository->find($classeId);
            if (!$classe) {
                $this->addFlash('error', 'Class not found.');
                return $this->redirectToRoute('app_admin_course_document_edit', ['id' => $id]);
            }

            $document->setTitle($title);
            $document->setDescription($description);
            $document->setClasse($classe);

            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('document_file');
            if ($uploadedFile) {
                $document->setDocumentFile($uploadedFile);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Document updated successfully.');
            return $this->redirectToRoute('app_admin_course_documents');
        }

        return $this->render('Gestion_Evaluation/admin_document/edit.html.twig', [
            'document' => $document,
            'classes' => $classes,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_course_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $document = $this->documentRepository->find($id);
        
        if (!$document) {
            $this->addFlash('error', 'Document not found.');
            return $this->redirectToRoute('app_admin_course_documents');
        }

        if (!$this->isCsrfTokenValid('delete_document_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_course_documents');
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document deleted successfully.');
        return $this->redirectToRoute('app_admin_course_documents');
    }
}
