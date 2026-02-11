<?php

namespace App\Controller;

use App\Entity\Module;
use App\Entity\Course;
use App\Entity\Contenu;
use App\Form\ModuleType;
use App\Form\CourseType;
use App\Form\ContenuFormType;
use App\Repository\ModuleRepository;
use App\Repository\CourseRepository;
use App\Repository\ContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProgrammeController extends AbstractController
{
    #[Route('/programme', name: 'app_programme')]
    public function index(): Response
    {
        return $this->render('Gestion_Program/programme/index.html.twig');
    }

    // ============ MODULES ============

    #[Route('/programme/modules', name: 'app_programme_modules')]
    public function modules(ModuleRepository $moduleRepository): Response
    {
        $modules = $moduleRepository->findAll();
        
        return $this->render('Gestion_Program/programme/modules.html.twig', [
            'modules' => $modules
        ]);
    }

    #[Route('/programme/modules/new', name: 'app_programme_modules_new')]
    public function newModule(Request $request, EntityManagerInterface $em): Response
    {
        $module = new Module();
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($module);
            $em->flush();

            $this->addFlash('success', 'Module created successfully!');
            return $this->redirectToRoute('app_programme_modules');
        }

        return $this->render('Gestion_Program/programme/module_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/programme/modules/{id}/edit', name: 'app_programme_modules_edit')]
    public function editModule(Module $module, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $module->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Module updated successfully!');
            return $this->redirectToRoute('app_programme_modules');
        }

        return $this->render('Gestion_Program/programme/module_edit.html.twig', [
            'form' => $form->createView(),
            'module' => $module
        ]);
    }

    #[Route('/programme/modules/{id}/delete', name: 'app_programme_modules_delete', methods: ['POST'])]
    public function deleteModule(Module $module, EntityManagerInterface $em): Response
    {
        $em->remove($module);
        $em->flush();

        $this->addFlash('success', 'Module deleted successfully!');
        return $this->redirectToRoute('app_programme_modules');
    }

    // ============ COURSES ============

    #[Route('/programme/courses', name: 'app_programme_courses')]
    public function courses(CourseRepository $courseRepository): Response
    {
        $courses = $courseRepository->findAll();
        
        return $this->render('Gestion_Program/programme/courses.html.twig', [
            'courses' => $courses
        ]);
    }

    #[Route('/programme/courses/new', name: 'app_programme_courses_new')]
    public function newCourse(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();

            $this->addFlash('success', 'Course created successfully!');
            return $this->redirectToRoute('app_programme_courses');
        }

        return $this->render('Gestion_Program/programme/course_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/programme/courses/{id}/edit', name: 'app_programme_courses_edit')]
    public function editCourse(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $course->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Course updated successfully!');
            return $this->redirectToRoute('app_programme_courses');
        }

        return $this->render('Gestion_Program/programme/course_edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course
        ]);
    }

    #[Route('/programme/courses/{id}/delete', name: 'app_programme_courses_delete', methods: ['POST'])]
    public function deleteCourse(Course $course, EntityManagerInterface $em): Response
    {
        $em->remove($course);
        $em->flush();

        $this->addFlash('success', 'Course deleted successfully!');
        return $this->redirectToRoute('app_programme_courses');
    }

    // ============ CONTENUS ============

    #[Route('/programme/contenus', name: 'app_programme_contenus')]
    public function contenus(ContenuRepository $contenuRepository): Response
    {
        $contenus = $contenuRepository->findAll();
        
        return $this->render('Gestion_Program/programme/contenus.html.twig', [
            'contenus' => $contenus
        ]);
    }

    #[Route('/programme/contenus/new', name: 'app_programme_contenus_new')]
    public function newContenu(Request $request, EntityManagerInterface $em): Response
    {
        $contenu = new Contenu();
        $form = $this->createForm(ContenuFormType::class, $contenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($contenu);
            $em->flush();

            $this->addFlash('success', 'Content created successfully!');
            return $this->redirectToRoute('app_programme_contenus');
        }

        return $this->render('Gestion_Program/programme/contenu_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/programme/contenus/{id}/edit', name: 'app_programme_contenus_edit')]
    public function editContenu(Contenu $contenu, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ContenuFormType::class, $contenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contenu->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Content updated successfully!');
            return $this->redirectToRoute('app_programme_contenus');
        }

        return $this->render('Gestion_Program/programme/contenu_edit.html.twig', [
            'form' => $form->createView(),
            'contenu' => $contenu
        ]);
    }

    #[Route('/programme/contenus/{id}/delete', name: 'app_programme_contenus_delete', methods: ['POST'])]
    public function deleteContenu(Contenu $contenu, EntityManagerInterface $em): Response
    {
        $em->remove($contenu);
        $em->flush();

        $this->addFlash('success', 'Content deleted successfully!');
        return $this->redirectToRoute('app_programme_contenus');
    }
}
