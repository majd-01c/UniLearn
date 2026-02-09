<?php

namespace App\Service;

use App\Entity\BuildProgram;
use App\Entity\BuildProgramModule;
use App\Entity\BuildProgramCourse;
use App\Entity\BuildProgramContenu;
use App\Entity\Program;
use App\Entity\Module;
use App\Entity\Course;
use App\Entity\Contenu;
use Doctrine\ORM\EntityManagerInterface;

class BuildProgramService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create a BuildProgram instance from a Program template
     */
    public function createFromProgram(
        Program $program,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $imageUrl = null,
        string $level = 'Basique',
        bool $copyModules = true
    ): BuildProgram {
        $buildProgram = new BuildProgram();
        $buildProgram->setProgram($program);
        $buildProgram->setStartDate($startDate);
        $buildProgram->setEndDate($endDate);
        $buildProgram->setImageUrl($imageUrl ?? $program->getImageUrl());
        $buildProgram->setLevel($level);

        $this->entityManager->persist($buildProgram);

        // Automatically copy all modules from the template if requested
        if ($copyModules) {
            foreach ($program->getModules() as $programModule) {
                $this->addModule($buildProgram, $programModule->getModule(), true);
            }
        }

        $this->entityManager->flush();

        return $buildProgram;
    }

    /**
     * Add a Module to a BuildProgram
     */
    public function addModule(
        BuildProgram $buildProgram,
        Module $module,
        bool $copyCourses = false
    ): BuildProgramModule {
        // Check if module already exists
        foreach ($buildProgram->getModules() as $existing) {
            if ($existing->getModule() === $module) {
                return $existing;
            }
        }

        $buildProgramModule = new BuildProgramModule();
        $buildProgramModule->setBuildProgram($buildProgram);
        $buildProgramModule->setModule($module);

        $buildProgram->addModule($buildProgramModule);
        $this->entityManager->persist($buildProgramModule);

        // Automatically copy all courses from the module if requested
        if ($copyCourses) {
            foreach ($module->getCourses() as $moduleCourse) {
                $this->addCourse($buildProgramModule, $moduleCourse->getCourse(), true);
            }
        }

        $this->entityManager->flush();

        return $buildProgramModule;
    }

    /**
     * Add a Course to a BuildProgramModule
     */
    public function addCourse(
        BuildProgramModule $buildProgramModule,
        Course $course,
        bool $copyContenus = false
    ): BuildProgramCourse {
        // Check if course already exists
        foreach ($buildProgramModule->getCourses() as $existing) {
            if ($existing->getCourse() === $course) {
                return $existing;
            }
        }

        $buildProgramCourse = new BuildProgramCourse();
        $buildProgramCourse->setBuildProgramModule($buildProgramModule);
        $buildProgramCourse->setCourse($course);

        $buildProgramModule->addCourse($buildProgramCourse);
        $this->entityManager->persist($buildProgramCourse);

        // Automatically copy all contenus from the course if requested
        if ($copyContenus) {
            foreach ($course->getContenus() as $courseContenu) {
                $this->addContenu($buildProgramCourse, $courseContenu->getContenu());
            }
        }

        $this->entityManager->flush();

        return $buildProgramCourse;
    }

    /**
     * Add a Contenu to a BuildProgramCourse
     */
    public function addContenu(
        BuildProgramCourse $buildProgramCourse,
        Contenu $contenu
    ): BuildProgramContenu {
        // Check if contenu already exists
        foreach ($buildProgramCourse->getContenus() as $existing) {
            if ($existing->getContenu() === $contenu) {
                return $existing;
            }
        }

        $buildProgramContenu = new BuildProgramContenu();
        $buildProgramContenu->setBuildProgramCourse($buildProgramCourse);
        $buildProgramContenu->setContenu($contenu);

        $buildProgramCourse->addContenu($buildProgramContenu);
        $this->entityManager->persist($buildProgramContenu);
        $this->entityManager->flush();

        return $buildProgramContenu;
    }

    /**
     * Remove a Module from a BuildProgram
     */
    public function removeModule(BuildProgram $buildProgram, Module $module): void
    {
        foreach ($buildProgram->getModules() as $buildProgramModule) {
            if ($buildProgramModule->getModule() === $module) {
                $buildProgram->removeModule($buildProgramModule);
                $this->entityManager->remove($buildProgramModule);
                $this->entityManager->flush();
                return;
            }
        }
    }

    /**
     * Remove a Course from a BuildProgramModule
     */
    public function removeCourse(BuildProgramModule $buildProgramModule, Course $course): void
    {
        foreach ($buildProgramModule->getCourses() as $buildProgramCourse) {
            if ($buildProgramCourse->getCourse() === $course) {
                $buildProgramModule->removeCourse($buildProgramCourse);
                $this->entityManager->remove($buildProgramCourse);
                $this->entityManager->flush();
                return;
            }
        }
    }

    /**
     * Remove a Contenu from a BuildProgramCourse
     */
    public function removeContenu(BuildProgramCourse $buildProgramCourse, Contenu $contenu): void
    {
        foreach ($buildProgramCourse->getContenus() as $buildProgramContenu) {
            if ($buildProgramContenu->getContenu() === $contenu) {
                $buildProgramCourse->removeContenu($buildProgramContenu);
                $this->entityManager->remove($buildProgramContenu);
                $this->entityManager->flush();
                return;
            }
        }
    }

    /**
     * Get the full hierarchy of a BuildProgram
     */
    public function getHierarchy(BuildProgram $buildProgram): array
    {
        $hierarchy = [
            'id' => $buildProgram->getId(),
            'program' => [
                'id' => $buildProgram->getProgram()->getId(),
                'name' => $buildProgram->getProgram()->getName(),
            ],
            'startDate' => $buildProgram->getStartDate()->format('Y-m-d'),
            'endDate' => $buildProgram->getEndDate()->format('Y-m-d'),
            'level' => $buildProgram->getLevel(),
            'modules' => [],
        ];

        foreach ($buildProgram->getModules() as $buildProgramModule) {
            $module = $buildProgramModule->getModule();
            $moduleData = [
                'id' => $module->getId(),
                'name' => $module->getName(),
                'courses' => [],
            ];

            foreach ($buildProgramModule->getCourses() as $buildProgramCourse) {
                $course = $buildProgramCourse->getCourse();
                $courseData = [
                    'id' => $course->getId(),
                    'title' => $course->getTitle(),
                    'contenus' => [],
                ];

                foreach ($buildProgramCourse->getContenus() as $buildProgramContenu) {
                    $contenu = $buildProgramContenu->getContenu();
                    $courseData['contenus'][] = [
                        'id' => $contenu->getId(),
                        'title' => $contenu->getTitle(),
                        'type' => $contenu->getType()->value,
                    ];
                }

                $moduleData['courses'][] = $courseData;
            }

            $hierarchy['modules'][] = $moduleData;
        }

        return $hierarchy;
    }
}
