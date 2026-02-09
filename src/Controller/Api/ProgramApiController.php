<?php

namespace App\Controller\Api;

use App\Entity\Program;
use App\Repository\ProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/programs', name: 'api_program_')]
class ProgramApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProgramRepository $programRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $programs = $this->programRepository->findAll();
        
        $data = array_map(function (Program $program) {
            return [
                'id' => $program->getId(),
                'name' => $program->getName(),
                'description' => $program->getDescription(),
                'duration' => $program->getDuration(),
                'durationUnit' => $program->getDurationUnit()->value,
                'imageUrl' => $program->getImageUrl(),
            ];
        }, $programs);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $program = $this->programRepository->find($id);
        
        if (!$program) {
            return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $program->getId(),
            'name' => $program->getName(),
            'description' => $program->getDescription(),
            'duration' => $program->getDuration(),
            'durationUnit' => $program->getDurationUnit()->value,
            'imageUrl' => $program->getImageUrl(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || !isset($data['durationUnit'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $program = new Program();
        $program->setName($data['name']);
        $program->setDescription($data['description'] ?? null);
        $program->setDuration($data['duration'] ?? null);
        $program->setDurationUnit(\App\Enum\PeriodUnit::from($data['durationUnit']));
        $program->setImageUrl($data['imageUrl'] ?? null);

        $this->entityManager->persist($program);
        $this->entityManager->flush();

        return $this->json([
            'id' => $program->getId(),
            'name' => $program->getName(),
            'description' => $program->getDescription(),
            'duration' => $program->getDuration(),
            'durationUnit' => $program->getDurationUnit()->value,
            'imageUrl' => $program->getImageUrl(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $program = $this->programRepository->find($id);
        
        if (!$program) {
            return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $program->setName($data['name']);
        }
        if (isset($data['description'])) {
            $program->setDescription($data['description']);
        }
        if (isset($data['duration'])) {
            $program->setDuration($data['duration']);
        }
        if (isset($data['durationUnit'])) {
            $program->setDurationUnit(\App\Enum\PeriodUnit::from($data['durationUnit']));
        }
        if (isset($data['imageUrl'])) {
            $program->setImageUrl($data['imageUrl']);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $program->getId(),
            'name' => $program->getName(),
            'description' => $program->getDescription(),
            'duration' => $program->getDuration(),
            'durationUnit' => $program->getDurationUnit()->value,
            'imageUrl' => $program->getImageUrl(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $program = $this->programRepository->find($id);
        
        if (!$program) {
            return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($program);
        $this->entityManager->flush();

        return $this->json(['message' => 'Program deleted successfully']);
    }

    #[Route('/{id}/hierarchy', name: 'hierarchy', methods: ['GET'])]
    public function hierarchy(int $id): JsonResponse
    {
        $program = $this->programRepository->find($id);
        
        if (!$program) {
            return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
        }

        $hierarchy = [
            'id' => $program->getId(),
            'name' => $program->getName(),
            'modules' => [],
        ];

        foreach ($program->getModules() as $programModule) {
            $module = $programModule->getModule();
            $moduleData = [
                'id' => $module->getId(),
                'name' => $module->getName(),
                'courses' => [],
            ];

            foreach ($module->getCourses() as $moduleCourse) {
                $course = $moduleCourse->getCourse();
                $courseData = [
                    'id' => $course->getId(),
                    'title' => $course->getTitle(),
                    'contenus' => [],
                ];

                foreach ($course->getContenus() as $courseContenu) {
                    $contenu = $courseContenu->getContenu();
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

        return $this->json($hierarchy);
    }
}
