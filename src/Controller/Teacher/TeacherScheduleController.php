<?php

namespace App\Controller\Teacher;

use App\Entity\User;
use App\Repository\ScheduleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher/emploi-du-temps')]
#[IsGranted('ROLE_TEACHER')]
class TeacherScheduleController extends AbstractController
{
    #[Route('', name: 'app_teacher_schedule')]
    public function index(ScheduleRepository $scheduleRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get all schedules where this teacher is assigned
        $schedules = $scheduleRepository->findBy(['teacher' => $user]);

        // Organize by day
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $weekSchedule = array_fill_keys($days, []);

        foreach ($schedules as $schedule) {
            $day = strtolower($schedule->getDayOfWeek());
            if (array_key_exists($day, $weekSchedule)) {
                $weekSchedule[$day][] = $schedule;
            }
        }

        // Sort each day by start time
        foreach ($weekSchedule as &$daySchedules) {
            usort($daySchedules, fn($a, $b) => $a->getStartTime() <=> $b->getStartTime());
        }

        return $this->render('Gestion_Evaluation/teacher/schedule.html.twig', [
            'weekSchedule' => $weekSchedule,
            'teacher'      => $user,
        ]);
    }
}
