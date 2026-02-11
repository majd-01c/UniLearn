<?php

namespace App\Controller\Admin;

use App\Entity\Schedule;
use App\Form\AdminScheduleType;
use App\Repository\ScheduleRepository;
use App\Repository\ClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/schedules')]
#[IsGranted('ROLE_ADMIN')]
class AdminScheduleController extends AbstractController
{
    #[Route('/', name: 'app_admin_schedules')]
    public function index(ClasseRepository $classeRepository): Response
    {
        $classes = $classeRepository->findAll();
        
        return $this->render('gestion_user/admin/schedule/index.html.twig', [
            'classes' => $classes,
        ]);
    }

    #[Route('/classe/{id}', name: 'app_admin_schedule_by_classe')]
    public function scheduleByClasse(
        int $id,
        ScheduleRepository $scheduleRepository,
        ClasseRepository $classeRepository
    ): Response {
        $classe = $classeRepository->find($id);
        
        if (!$classe) {
            throw $this->createNotFoundException('Classe not found');
        }

        $schedules = $scheduleRepository->findCurrentSchedule($classe);
        
        return $this->render('gestion_user/admin/schedule/by_classe.html.twig', [
            'classe' => $classe,
            'schedules' => $schedules,
        ]);
    }

    #[Route('/new', name: 'app_admin_schedule_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schedule = new Schedule();
        $form = $this->createForm(AdminScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($schedule);
            $entityManager->flush();

            $this->addFlash('success', 'Emploi du temps créé avec succès.');

            return $this->redirectToRoute('app_admin_schedules');
        }

        return $this->render('gestion_user/admin/schedule/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_schedule_edit')]
    public function edit(
        Request $request,
        Schedule $schedule,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(AdminScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Emploi du temps mis à jour avec succès.');

            return $this->redirectToRoute('app_admin_schedules');
        }

        return $this->render('gestion_user/admin/schedule/edit.html.twig', [
            'form' => $form->createView(),
            'schedule' => $schedule,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_schedule_delete', methods: ['POST'])]
    public function delete(
        Schedule $schedule,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($schedule);
        $entityManager->flush();

        $this->addFlash('success', 'Emploi du temps supprimé avec succès.');

        return $this->redirectToRoute('app_admin_schedules');
    }
}
