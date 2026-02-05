<?php

namespace App\Controller\Api;

use App\Entity\BuildProgram;
use App\Repository\BuildProgramRepository;
use App\Repository\ProgramRepository;
use App\Service\BuildProgramService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/build-programs', name: 'api_build_program_')]
class BuildProgramApiController extends AbstractController
{
    public function __construct(
        private BuildProgramRepository $buildProgramRepository,
        private ProgramRepository $programRepository,
        private BuildProgramService $buildProgramService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $buildPrograms = $this->buildProgramRepository->findAll();
        
        $data = array_map(function (BuildProgram $bp) {
            return [
                'id' => $bp->getId(),
                'program' => [
                    'id' => $bp->getProgram()->getId(),
                    'name' => $bp->getProgram()->getName(),
                ],
                'startDate' => $bp->getStartDate()->format('Y-m-d'),
                'endDate' => $bp->getEndDate()->format('Y-m-d'),
                'level' => $bp->getLevel(),
                'imageUrl' => $bp->getImageUrl(),
            ];
        }, $buildPrograms);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $buildProgram = $this->buildProgramRepository->find($id);
        
        if (!$buildProgram) {
            return $this->json(['error' => 'BuildProgram not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $buildProgram->getId(),
            'program' => [
                'id' => $buildProgram->getProgram()->getId(),
                'name' => $buildProgram->getProgram()->getName(),
            ],
            'startDate' => $buildProgram->getStartDate()->format('Y-m-d'),
            'endDate' => $buildProgram->getEndDate()->format('Y-m-d'),
            'level' => $buildProgram->getLevel(),
            'imageUrl' => $buildProgram->getImageUrl(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['programId']) || !isset($data['startDate']) || !isset($data['endDate'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $program = $this->programRepository->find($data['programId']);
        if (!$program) {
            return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
        }

        $buildProgram = $this->buildProgramService->createFromProgram(
            $program,
            new \DateTime($data['startDate']),
            new \DateTime($data['endDate']),
            $data['imageUrl'] ?? null,
            $data['level'] ?? 'Basique',
            $data['copyModules'] ?? true
        );

        return $this->json([
            'id' => $buildProgram->getId(),
            'program' => [
                'id' => $buildProgram->getProgram()->getId(),
                'name' => $buildProgram->getProgram()->getName(),
            ],
            'startDate' => $buildProgram->getStartDate()->format('Y-m-d'),
            'endDate' => $buildProgram->getEndDate()->format('Y-m-d'),
            'level' => $buildProgram->getLevel(),
            'imageUrl' => $buildProgram->getImageUrl(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/hierarchy', name: 'hierarchy', methods: ['GET'])]
    public function hierarchy(int $id): JsonResponse
    {
        $buildProgram = $this->buildProgramRepository->find($id);
        
        if (!$buildProgram) {
            return $this->json(['error' => 'BuildProgram not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->buildProgramService->getHierarchy($buildProgram));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $buildProgram = $this->buildProgramRepository->find($id);
        
        if (!$buildProgram) {
            return $this->json(['error' => 'BuildProgram not found'], Response::HTTP_NOT_FOUND);
        }

        $this->buildProgramRepository->remove($buildProgram, true);

        return $this->json(['message' => 'BuildProgram deleted successfully']);
    }
}
