<?php

namespace App\Service;

use App\Entity\Classe;
use App\Entity\ClasseModule;
use App\Entity\ClasseCourse;
use App\Entity\ClasseContenu;
use App\Entity\Program;
use App\Entity\BuildProgram;
use App\Enum\ClasseStatus;
use Doctrine\ORM\EntityManagerInterface;

class ClasseService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create a Classe from a Program template
     */
    public function createFromProgram(
        Program $program,
        string $name,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $imageUrl = null,
        ClasseStatus $status = ClasseStatus::INACTIVE
    ): Classe {
        $classe = new Classe();
        $classe->setName($name);
        $classe->setProgram($program);
        $classe->setStartDate($startDate);
        $classe->setEndDate($endDate);
        $classe->setImageUrl($imageUrl ?? $program->getImageUrl());
        $classe->setStatus($status);

        $this->entityManager->persist($classe);
        $this->entityManager->flush();

        return $classe;
    }

    /**
     * Create a Classe from a BuildProgram instance
     * This copies all the content structure from the BuildProgram
     */
    public function createFromBuildProgram(
        BuildProgram $buildProgram,
        string $name,
        ?string $imageUrl = null,
        ClasseStatus $status = ClasseStatus::INACTIVE
    ): Classe {
        $classe = $this->createFromProgram(
            $buildProgram->getProgram(),
            $name,
            $buildProgram->getStartDate(),
            $buildProgram->getEndDate(),
            $imageUrl ?? $buildProgram->getImageUrl(),
            $status
        );

        // Copy the entire hierarchy from BuildProgram
        $this->syncContentFromBuildProgram($classe, $buildProgram);

        return $classe;
    }

    /**
     * Sync content from a BuildProgram to a Classe
     * This copies all modules, courses, and contenus
     */
    public function syncContentFromBuildProgram(Classe $classe, BuildProgram $buildProgram): void
    {
        // Clear existing modules
        foreach ($classe->getModules() as $existingModule) {
            $this->entityManager->remove($existingModule);
        }
        $this->entityManager->flush();

        // Copy modules from BuildProgram
        foreach ($buildProgram->getModules() as $buildProgramModule) {
            $classeModule = new ClasseModule();
            $classeModule->setClasse($classe);
            $classeModule->setModule($buildProgramModule->getModule());
            $classe->addModule($classeModule);
            $this->entityManager->persist($classeModule);

            // Copy courses
            foreach ($buildProgramModule->getCourses() as $buildProgramCourse) {
                $classeCourse = new ClasseCourse();
                $classeCourse->setClasseModule($classeModule);
                $classeCourse->setCourse($buildProgramCourse->getCourse());
                $classeModule->addCourse($classeCourse);
                $this->entityManager->persist($classeCourse);

                // Copy contenus
                foreach ($buildProgramCourse->getContenus() as $buildProgramContenu) {
                    $classeContenu = new ClasseContenu();
                    $classeContenu->setClasseCourse($classeCourse);
                    $classeContenu->setContenu($buildProgramContenu->getContenu());
                    $classeCourse->addContenu($classeContenu);
                    $this->entityManager->persist($classeContenu);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Activate a classe
     */
    public function activateClasse(Classe $classe): void
    {
        $classe->activate();
        $this->entityManager->flush();
    }

    /**
     * Deactivate a classe
     */
    public function deactivateClasse(Classe $classe): void
    {
        $classe->deactivate();
        $this->entityManager->flush();
    }

    /**
     * Add a Module to a Classe
     */
    public function addModule(Classe $classe, \App\Entity\Module $module): ClasseModule
    {
        // Check if module already exists
        foreach ($classe->getModules() as $existing) {
            if ($existing->getModule() === $module) {
                return $existing;
            }
        }

        $classeModule = new ClasseModule();
        $classeModule->setClasse($classe);
        $classeModule->setModule($module);

        $classe->addModule($classeModule);
        $this->entityManager->persist($classeModule);
        $this->entityManager->flush();

        return $classeModule;
    }

    /**
     * Add a Course to a ClasseModule
     */
    public function addCourse(ClasseModule $classeModule, \App\Entity\Course $course): ClasseCourse
    {
        // Check if course already exists
        foreach ($classeModule->getCourses() as $existing) {
            if ($existing->getCourse() === $course) {
                return $existing;
            }
        }

        $classeCourse = new ClasseCourse();
        $classeCourse->setClasseModule($classeModule);
        $classeCourse->setCourse($course);

        $classeModule->addCourse($classeCourse);
        $this->entityManager->persist($classeCourse);
        $this->entityManager->flush();

        return $classeCourse;
    }

    /**
     * Add a Contenu to a ClasseCourse
     */
    public function addContenu(ClasseCourse $classeCourse, \App\Entity\Contenu $contenu): ClasseContenu
    {
        // Check if contenu already exists
        foreach ($classeCourse->getContenus() as $existing) {
            if ($existing->getContenu() === $contenu) {
                return $existing;
            }
        }

        $classeContenu = new ClasseContenu();
        $classeContenu->setClasseCourse($classeCourse);
        $classeContenu->setContenu($contenu);

        $classeCourse->addContenu($classeContenu);
        $this->entityManager->persist($classeContenu);
        $this->entityManager->flush();

        return $classeContenu;
    }

    /**
     * Get the full hierarchy of a Classe
     */
    public function getHierarchy(Classe $classe): array
    {
        $hierarchy = [
            'id' => $classe->getId(),
            'name' => $classe->getName(),
            'program' => [
                'id' => $classe->getProgram()->getId(),
                'name' => $classe->getProgram()->getName(),
            ],
            'startDate' => $classe->getStartDate()->format('Y-m-d'),
            'endDate' => $classe->getEndDate()->format('Y-m-d'),
            'status' => $classe->getStatus()->value,
            'isActive' => $classe->isActive(),
            'modules' => [],
        ];

        foreach ($classe->getModules() as $classeModule) {
            $module = $classeModule->getModule();
            $moduleData = [
                'id' => $module->getId(),
                'name' => $module->getName(),
                'courses' => [],
            ];

            foreach ($classeModule->getCourses() as $classeCourse) {
                $course = $classeCourse->getCourse();
                $courseData = [
                    'id' => $course->getId(),
                    'title' => $course->getTitle(),
                    'contenus' => [],
                ];

                foreach ($classeCourse->getContenus() as $classeContenu) {
                    $contenu = $classeContenu->getContenu();
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

    /**
     * Get all active classes
     */
    public function getActiveClasses(): array
    {
        return $this->entityManager->getRepository(Classe::class)
            ->findBy(['status' => ClasseStatus::ACTIVE]);
    }

    /**
     * Get all inactive classes
     */
    public function getInactiveClasses(): array
    {
        return $this->entityManager->getRepository(Classe::class)
            ->findBy(['status' => ClasseStatus::INACTIVE]);
    }
}
