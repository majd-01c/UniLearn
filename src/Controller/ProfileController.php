<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * View and edit user profile
     */
    #[Route('', name: 'profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $profile = $user->getProfile();

        if (!$profile) {
            $this->addFlash('error', 'Profile not found.');
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'Profile updated successfully.');
                return $this->redirectToRoute('profile_edit');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating profile: ' . $e->getMessage());
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    /**
     * Change password
     */
    #[Route('/change-password', name: 'profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Verify current password
                $currentPassword = $form->get('currentPassword')->getData();
                if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Current password is incorrect.');
                    return $this->redirectToRoute('profile_change_password');
                }

                // Hash and set new password
                $newPassword = $form->get('newPassword')->getData();
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setMustChangePassword(false);
                $user->setTempPasswordGeneratedAt(null);
                $user->setUpdatedAt(new \DateTimeImmutable());

                $this->entityManager->flush();

                $this->addFlash('success', 'Password changed successfully.');
                return $this->redirectToRoute('profile_edit');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error changing password: ' . $e->getMessage());
            }
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'mustChange' => $user->isMustChangePassword(),
        ]);
    }
}
