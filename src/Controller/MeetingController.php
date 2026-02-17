<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MeetingController extends AbstractController
{
    #[Route('/meeting', name: 'app_meeting_index')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('meeting/index.html.twig');
    }

    #[Route('/meeting/{room}', name: 'app_meeting_room')]
    #[IsGranted('ROLE_USER')]
    public function joinRoom(string $room): Response
    {
        $user = $this->getUser();
        
        return $this->render('test_jitsi_room.html.twig', [
            'jitsi_host' => $_ENV['JITSI_HOST'],
            'room' => $room,
            'username' => $user ? $user->getUserIdentifier() : 'Guest'
        ]);
    }
}
