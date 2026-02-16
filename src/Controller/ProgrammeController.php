<?php

namespace App\Controller;

use App\Entity\Module;
use App\Entity\Course;
use App\Entity\Contenu;
use App\Entity\Program;
use App\Entity\ProgramModule;
use App\Entity\ModuleCourse;
use App\Entity\CourseContenu;
use App\Form\ModuleType;
use App\Form\CourseType;
use App\Form\ContenuFormType;
use App\Form\ProgramType;
use App\Repository\ModuleRepository;
use App\Repository\CourseRepository;
use App\Repository\ContenuRepository;
use App\Repository\ProgramRepository;
use App\Repository\ProgramModuleRepository;
use App\Repository\ModuleCourseRepository;
use App\Repository\CourseContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProgrammeController extends AbstractController
{
    // ============ PROGRAMS INDEX ============

    #[Route('/programme', name: 'app_programme')]
    public function index(ProgramRepository $programRepository): Response
    {
        $programs = $programRepository->findAll();
        
        return $this->render('Gestion_Program/programme/index.html.twig', [
            'programs' => $programs
        ]);
    }

    // ============ PROGRAM CRUD ============

    #[Route('/programme/programs', name: 'app_programme_programs')]
    public function programs(Request $request, ProgramRepository $programRepository): Response
    {
        $name = $request->query->get('name', '');
        $status = $request->query->get('status', '');
        
        $criteria = [];
        if ($status === 'published') {
            $criteria['published'] = true;
        } elseif ($status === 'draft') {
            $criteria['published'] = false;
        }
        
        if ($name || $criteria) {
            $qb = $programRepository->createQueryBuilder('p');
            
            if ($name) {
                $qb->andWhere('p.name LIKE :name')
                   ->setParameter('name', '%' . $name . '%');
            }
            
            if ($status === 'published') {
                $qb->andWhere('p.published = :published')
                   ->setParameter('published', true);
            } elseif ($status === 'draft') {
                $qb->andWhere('p.published = :published')
                   ->setParameter('published', false);
            }
            
            $qb->orderBy('p.createdAt', 'DESC');
            $programs = $qb->getQuery()->getResult();
        } else {
            $programs = $programRepository->findBy([], ['createdAt' => 'DESC']);
        }
        
        return $this->render('Gestion_Program/programme/programs.html.twig', [
            'programs' => $programs,
            'filters' => [
                'name' => $name,
                'status' => $status,
            ]
        ]);
    }

    #[Route('/programme/programs/new', name: 'app_programme_programs_new')]
    public function newProgram(Request $request, EntityManagerInterface $em): Response
    {
        $program = new Program();
        $form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($program);
            $em->flush();

            $this->addFlash('success', 'Program created successfully!');
            return $this->redirectToRoute('app_programme_programs');
        }

        return $this->render('Gestion_Program/programme/program_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/programme/programs/{id}', name: 'app_programme_programs_show', requirements: ['id' => '\d+'])]
    public function showProgram(Program $program, ModuleRepository $moduleRepository): Response
    {
        $allModules = $moduleRepository->findAll();
        $assignedModuleIds = array_map(fn($pm) => $pm->getModule()->getId(), $program->getModules()->toArray());
        $availableModules = array_filter($allModules, fn($m) => !in_array($m->getId(), $assignedModuleIds));
        
        return $this->render('Gestion_Program/programme/program_show.html.twig', [
            'program' => $program,
            'availableModules' => $availableModules
        ]);
    }

    #[Route('/programme/programs/{id}/edit', name: 'app_programme_programs_edit')]
    public function editProgram(Program $program, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $program->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Program updated successfully!');
            return $this->redirectToRoute('app_programme_programs');
        }

        return $this->render('Gestion_Program/programme/program_edit.html.twig', [
            'form' => $form->createView(),
            'program' => $program
        ]);
    }

    #[Route('/programme/programs/{id}/delete', name: 'app_programme_programs_delete', methods: ['POST'])]
    public function deleteProgram(Program $program, EntityManagerInterface $em): Response
    {
        $em->remove($program);
        $em->flush();

        $this->addFlash('success', 'Program deleted successfully!');
        return $this->redirectToRoute('app_programme_programs');
    }

    // ============ PROGRAM-MODULE RELATIONSHIPS ============

    #[Route('/programme/programs/{id}/add-module', name: 'app_programme_programs_add_module', methods: ['POST'])]
    public function addModuleToProgram(Program $program, Request $request, EntityManagerInterface $em, ModuleRepository $moduleRepository): Response
    {
        $moduleId = $request->request->get('module_id');
        $module = $moduleRepository->find($moduleId);

        if ($module) {
            $programModule = new ProgramModule();
            $programModule->setProgram($program);
            $programModule->setModule($module);
            $em->persist($programModule);
            $em->flush();

            $this->addFlash('success', 'Module added to program successfully!');
        }

        return $this->redirectToRoute('app_programme_programs_show', ['id' => $program->getId()]);
    }

    #[Route('/programme/programs/{id}/remove-module/{moduleId}', name: 'app_programme_programs_remove_module', methods: ['POST'])]
    public function removeModuleFromProgram(Program $program, int $moduleId, EntityManagerInterface $em, ProgramModuleRepository $repo): Response
    {
        $programModule = $repo->findOneBy(['program' => $program, 'module' => $moduleId]);

        if ($programModule) {
            $em->remove($programModule);
            $em->flush();

            $this->addFlash('success', 'Module removed from program successfully!');
        }

        return $this->redirectToRoute('app_programme_programs_show', ['id' => $program->getId()]);
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
    public function contenus(Request $request, ContenuRepository $contenuRepository): Response
    {
        $title = $request->query->get('title', '');
        $type = $request->query->get('type', '');
        
        $qb = $contenuRepository->createQueryBuilder('c');
        
        if ($title) {
            $qb->andWhere('c.title LIKE :title')
               ->setParameter('title', '%' . $title . '%');
        }
        
        if ($type) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $type);
        }
        
        $qb->orderBy('c.createdAt', 'DESC');
        $contenus = $qb->getQuery()->getResult();
        
        return $this->render('Gestion_Program/programme/contenus.html.twig', [
            'contenus' => $contenus,
            'filters' => [
                'title' => $title,
                'type' => $type,
            ]
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

    // ============ MODULE-COURSE RELATIONSHIPS ============

    #[Route('/programme/modules/{id}', name: 'app_programme_modules_show', requirements: ['id' => '\d+'])]
    public function showModule(Module $module, CourseRepository $courseRepository): Response
    {
        $allCourses = $courseRepository->findAll();
        $assignedCourseIds = array_map(fn($mc) => $mc->getCourse()->getId(), $module->getCourses()->toArray());
        $availableCourses = array_filter($allCourses, fn($c) => !in_array($c->getId(), $assignedCourseIds));
        
        return $this->render('Gestion_Program/programme/module_show.html.twig', [
            'module' => $module,
            'availableCourses' => $availableCourses
        ]);
    }

    #[Route('/programme/modules/{id}/add-course', name: 'app_programme_modules_add_course', methods: ['POST'])]
    public function addCourseToModule(Module $module, Request $request, EntityManagerInterface $em, CourseRepository $courseRepository): Response
    {
        $courseId = $request->request->get('course_id');
        $course = $courseRepository->find($courseId);

        if ($course) {
            $moduleCourse = new ModuleCourse();
            $moduleCourse->setModule($module);
            $moduleCourse->setCourse($course);
            $em->persist($moduleCourse);
            $em->flush();

            $this->addFlash('success', 'Course added to module successfully!');
        }

        return $this->redirectToRoute('app_programme_modules_show', ['id' => $module->getId()]);
    }

    #[Route('/programme/modules/{id}/remove-course/{courseId}', name: 'app_programme_modules_remove_course', methods: ['POST'])]
    public function removeCourseFromModule(Module $module, int $courseId, EntityManagerInterface $em, ModuleCourseRepository $repo): Response
    {
        $moduleCourse = $repo->findOneBy(['module' => $module, 'course' => $courseId]);

        if ($moduleCourse) {
            $em->remove($moduleCourse);
            $em->flush();

            $this->addFlash('success', 'Course removed from module successfully!');
        }

        return $this->redirectToRoute('app_programme_modules_show', ['id' => $module->getId()]);
    }

    // ============ COURSE-CONTENU RELATIONSHIPS ============

    #[Route('/programme/courses/{id}', name: 'app_programme_courses_show', requirements: ['id' => '\d+'])]
    public function showCourse(Course $course, ContenuRepository $contenuRepository): Response
    {
        $allContenus = $contenuRepository->findAll();
        $assignedContenuIds = array_map(fn($cc) => $cc->getContenu()->getId(), $course->getContenus()->toArray());
        $availableContenus = array_filter($allContenus, fn($c) => !in_array($c->getId(), $assignedContenuIds));
        
        return $this->render('Gestion_Program/programme/course_show.html.twig', [
            'course' => $course,
            'availableContenus' => $availableContenus
        ]);
    }

    #[Route('/programme/courses/{id}/add-contenu', name: 'app_programme_courses_add_contenu', methods: ['POST'])]
    public function addContenuToCourse(Course $course, Request $request, EntityManagerInterface $em, ContenuRepository $contenuRepository): Response
    {
        $contenuId = $request->request->get('contenu_id');
        $contenu = $contenuRepository->find($contenuId);

        if ($contenu) {
            $courseContenu = new CourseContenu();
            $courseContenu->setCourse($course);
            $courseContenu->setContenu($contenu);
            $em->persist($courseContenu);
            $em->flush();

            $this->addFlash('success', 'Content added to course successfully!');
        }

        return $this->redirectToRoute('app_programme_courses_show', ['id' => $course->getId()]);
    }

    #[Route('/programme/courses/{id}/remove-contenu/{contenuId}', name: 'app_programme_courses_remove_contenu', methods: ['POST'])]
    public function removeContenuFromCourse(Course $course, int $contenuId, EntityManagerInterface $em, CourseContenuRepository $repo): Response
    {
        $courseContenu = $repo->findOneBy(['course' => $course, 'contenu' => $contenuId]);

        if ($courseContenu) {
            $em->remove($courseContenu);
            $em->flush();

            $this->addFlash('success', 'Content removed from course successfully!');
        }

        return $this->redirectToRoute('app_programme_courses_show', ['id' => $course->getId()]);
    }
}
