<?php

namespace App\Controller\Teacher;

use App\Entity\ClassMeeting;
use App\Entity\User;
use App\Repository\ClassMeetingRepository;
use App\Repository\TeacherClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher/meeting')]
#[IsGranted('ROLE_TEACHER')]
class TeacherMeetingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherClasseRepository $teacherClasseRepository,
        private ClassMeetingRepository $classMeetingRepository
    ) {}

    #[Route('/{teacherClasseId}/create', name: 'app_teacher_meeting_create', requirements: ['teacherClasseId' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Request $request, int $teacherClasseId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_meeting'.$teacherClasseId, $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
            }

            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $scheduledAt = $request->request->get('scheduled_at');
            $startNow = $request->request->has('start_now');

            if (empty($title)) {
                $this->addFlash('error', 'Meeting title is required.');
                return $this->render('Gestion_Program/teacher_meeting/create.html.twig', [
                    'teacherClasse' => $teacherClasse,
                ]);
            }

            $meeting = new ClassMeeting();
            $meeting->setTeacherClasse($teacherClasse);
            $meeting->setTitle($title);
            $meeting->setDescription($description ?: null);

            if ($scheduledAt) {
                $meeting->setScheduledAt(new \DateTime($scheduledAt));
            }

            if ($startNow) {
                $meeting->start();
            }

            $this->entityManager->persist($meeting);
            $this->entityManager->flush();

            if ($startNow) {
                $this->addFlash('success', 'Meeting started! Redirecting to video room...');
                return $this->redirectToRoute('app_teacher_meeting_join', [
                    'teacherClasseId' => $teacherClasseId,
                    'meetingId' => $meeting->getId()
                ]);
            }

            $this->addFlash('success', 'Meeting scheduled successfully!');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        return $this->render('Gestion_Program/teacher_meeting/create.html.twig', [
            'teacherClasse' => $teacherClasse,
        ]);
    }

    #[Route('/{teacherClasseId}', name: 'app_teacher_meeting_list', requirements: ['teacherClasseId' => '\d+'])]
    public function list(int $teacherClasseId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $meetings = $this->classMeetingRepository->findByTeacherClasse($teacherClasseId);

        return $this->render('Gestion_Program/teacher_meeting/list.html.twig', [
            'teacherClasse' => $teacherClasse,
            'meetings' => $meetings,
        ]);
    }

    #[Route('/{teacherClasseId}/{meetingId}/start', name: 'app_teacher_meeting_start', requirements: ['teacherClasseId' => '\d+', 'meetingId' => '\d+'], methods: ['POST'])]
    public function start(Request $request, int $teacherClasseId, int $meetingId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $meeting = $this->classMeetingRepository->find($meetingId);
        if (!$meeting || $meeting->getTeacherClasse()->getId() !== $teacherClasseId) {
            $this->addFlash('error', 'Meeting not found.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        if (!$this->isCsrfTokenValid('start_meeting'.$meetingId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        $meeting->start();
        $this->entityManager->flush();

        return $this->redirectToRoute('app_teacher_meeting_join', [
            'teacherClasseId' => $teacherClasseId,
            'meetingId' => $meetingId
        ]);
    }

    #[Route('/{teacherClasseId}/{meetingId}/join', name: 'app_teacher_meeting_join', requirements: ['teacherClasseId' => '\d+', 'meetingId' => '\d+'])]
    public function join(int $teacherClasseId, int $meetingId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $meeting = $this->classMeetingRepository->find($meetingId);
        if (!$meeting || $meeting->getTeacherClasse()->getId() !== $teacherClasseId) {
            $this->addFlash('error', 'Meeting not found.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        if (!$meeting->isLive()) {
            $meeting->start();
            $this->entityManager->flush();
        }

        return $this->render('Gestion_Program/teacher_meeting/room.html.twig', [
            'meeting' => $meeting,
            'teacherClasse' => $teacherClasse,
            'jitsi_host' => $_ENV['JITSI_HOST'] ?? 'meet.jit.si',
            'username' => $user->getName() . ' (Teacher)',
            'isTeacher' => true,
        ]);
    }

    #[Route('/{teacherClasseId}/{meetingId}/end', name: 'app_teacher_meeting_end', requirements: ['teacherClasseId' => '\d+', 'meetingId' => '\d+'], methods: ['POST'])]
    public function end(Request $request, int $teacherClasseId, int $meetingId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $meeting = $this->classMeetingRepository->find($meetingId);
        if (!$meeting || $meeting->getTeacherClasse()->getId() !== $teacherClasseId) {
            $this->addFlash('error', 'Meeting not found.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        if (!$this->isCsrfTokenValid('end_meeting'.$meetingId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        $meeting->end();
        $this->entityManager->flush();

        $this->addFlash('success', 'Meeting ended successfully.');
        return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
    }

    #[Route('/{teacherClasseId}/{meetingId}/delete', name: 'app_teacher_meeting_delete', requirements: ['teacherClasseId' => '\d+', 'meetingId' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $teacherClasseId, int $meetingId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $meeting = $this->classMeetingRepository->find($meetingId);
        if (!$meeting || $meeting->getTeacherClasse()->getId() !== $teacherClasseId) {
            $this->addFlash('error', 'Meeting not found.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        if (!$this->isCsrfTokenValid('delete_meeting'.$meetingId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
        }

        $this->entityManager->remove($meeting);
        $this->entityManager->flush();

        $this->addFlash('success', 'Meeting deleted successfully.');
        return $this->redirectToRoute('app_teacher_meeting_list', ['teacherClasseId' => $teacherClasseId]);
    }
}
