<?php

namespace App\Controller\Api;

use App\Entity\Classe;
use App\Repository\ClasseRepository;
use App\Repository\ProgramRepository;
use App\Repository\BuildProgramRepository;
use App\Service\ClasseService;
use App\Enum\ClasseStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/classes', name: 'api_classe_')]
class ClasseApiController extends AbstractController
{
    public function __construct(
        private ClasseRepository $classeRepository,
        private ProgramRepository $programRepository,
        private BuildProgramRepository $buildProgramRepository,
        private ClasseService $classeService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $classes = $this->classeRepository->findAll();
        
        $data = array_map(function (Classe $classe) {
            return [
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
                'imageUrl' => $classe->getImageUrl(),
            ];
        }, $classes);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $classe = $this->classeRepository->find($id);
        
        if (!$classe) {
            return $this->json(['error' => 'Classe not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
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
            'imageUrl' => $classe->getImageUrl(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->json(['error' => 'Missing required field: name'], Response::HTTP_BAD_REQUEST);
        }

        $classe = null;

        // Create from BuildProgram if provided
        if (isset($data['buildProgramId'])) {
            $buildProgram = $this->buildProgramRepository->find($data['buildProgramId']);
            if (!$buildProgram) {
                return $this->json(['error' => 'BuildProgram not found'], Response::HTTP_NOT_FOUND);
            }

            $status = isset($data['status']) ? ClasseStatus::from($data['status']) : ClasseStatus::INACTIVE;
            $classe = $this->classeService->createFromBuildProgram(
                $buildProgram,
                $data['name'],
                $data['imageUrl'] ?? null,
                $status
            );
        }
        // Create from Program if provided
        elseif (isset($data['programId']) && isset($data['startDate']) && isset($data['endDate'])) {
            $program = $this->programRepository->find($data['programId']);
            if (!$program) {
                return $this->json(['error' => 'Program not found'], Response::HTTP_NOT_FOUND);
            }

            $status = isset($data['status']) ? ClasseStatus::from($data['status']) : ClasseStatus::INACTIVE;
            $classe = $this->classeService->createFromProgram(
                $program,
                $data['name'],
                new \DateTime($data['startDate']),
                new \DateTime($data['endDate']),
                $data['imageUrl'] ?? null,
                $status
            );
        } else {
            return $this->json(['error' => 'Either buildProgramId or (programId, startDate, endDate) required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
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
            'imageUrl' => $classe->getImageUrl(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(int $id): JsonResponse
    {
        $classe = $this->classeRepository->find($id);
        
        if (!$classe) {
            return $this->json(['error' => 'Classe not found'], Response::HTTP_NOT_FOUND);
        }

        $this->classeService->activateClasse($classe);

        return $this->json([
            'id' => $classe->getId(),
            'status' => $classe->getStatus()->value,
            'isActive' => $classe->isActive(),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(int $id): JsonResponse
    {
        $classe = $this->classeRepository->find($id);
        
        if (!$classe) {
            return $this->json(['error' => 'Classe not found'], Response::HTTP_NOT_FOUND);
        }

        $this->classeService->deactivateClasse($classe);

        return $this->json([
            'id' => $classe->getId(),
            'status' => $classe->getStatus()->value,
            'isActive' => $classe->isActive(),
        ]);
    }

    #[Route('/{id}/hierarchy', name: 'hierarchy', methods: ['GET'])]
    public function hierarchy(int $id): JsonResponse
    {
        $classe = $this->classeRepository->find($id);
        
        if (!$classe) {
            return $this->json(['error' => 'Classe not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->classeService->getHierarchy($classe));
    }

    #[Route('/active', name: 'active', methods: ['GET'])]
    public function active(): JsonResponse
    {
        $classes = $this->classeService->getActiveClasses();
        
        $data = array_map(function (Classe $classe) {
            return [
                'id' => $classe->getId(),
                'name' => $classe->getName(),
                'program' => [
                    'id' => $classe->getProgram()->getId(),
                    'name' => $classe->getProgram()->getName(),
                ],
                'startDate' => $classe->getStartDate()->format('Y-m-d'),
                'endDate' => $classe->getEndDate()->format('Y-m-d'),
            ];
        }, $classes);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $classe = $this->classeRepository->find($id);
        
        if (!$classe) {
            return $this->json(['error' => 'Classe not found'], Response::HTTP_NOT_FOUND);
        }

        $this->classeRepository->remove($classe, true);

        return $this->json(['message' => 'Classe deleted successfully']);
    }
}
