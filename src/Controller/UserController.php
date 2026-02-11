<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Form\UserCreateType;
use App\Repository\UserRepository;
use App\Service\TempPasswordGenerator;
use App\Service\UserMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('Gestion_user/user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TempPasswordGenerator $tempPasswordGenerator,
        UserMailerService $userMailer,
        LoggerInterface $logger
    ): Response {
        $user = new User();
        $form = $this->createForm(UserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate temporary password
            $tempPassword = $tempPasswordGenerator->generate();
            
            // Hash and set password
            $hashedPassword = $passwordHasher->hashPassword($user, $tempPassword);
            $user->setPassword($hashedPassword);
            
            // Set user must change password on first login
            $user->setMustChangePassword(true);
            $user->setTempPasswordGeneratedAt(new \DateTimeImmutable());
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            // Set verification flags (code will be sent when user accesses verification page)
            $user->setIsVerified(false);
            $user->setNeedsVerification(true);
            
            // Create profile with form data
            $profile = new Profile();
            $profile->setFirstName($form->get('firstName')->getData());
            $profile->setLastName($form->get('lastName')->getData());
            $profile->setPhone($form->get('phone')->getData());
            $profile->setDescription($form->get('description')->getData());
            $profile->setUser($user);
            $user->setProfile($profile);
            
            // Persist both user and profile
            $entityManager->persist($user);
            $entityManager->persist($profile);
            $entityManager->flush();

            // Send welcome email with temporary password and verification code
            $userEmail = $user->getEmail();
            $logger->info('Attempting to send welcome email', ['email' => $userEmail]);
            
            if (empty($userEmail)) {
                $logger->error('Email is empty for new user');
                $this->addFlash('warning', sprintf(
                    'User created but email is empty! Temp password: %s, Verification code: %s',
                    $tempPassword,
                    $verificationCode
                ));
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }

            try {
                $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $userMailer->sendWelcomeEmail($user, $tempPassword, $loginUrl);
                
                $logger->info('Welcome email sent successfully to: ' . $userEmail);
                
                $this->addFlash('success', sprintf(
                    'User created! Welcome email sent to %s. Verification code will be sent when user logs in.',
                    $userEmail
                ));
            } catch (\Throwable $e) {
                $logger->error('Email sending failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                
                $this->addFlash('warning', sprintf(
                    'User created but email failed: %s. Temp password: %s',
                    $e->getMessage(),
                    $tempPassword
                ));
            }

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('Gestion_user/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('Gestion_user/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(UserCreateType::class, $user);
        
        // Pre-fill profile data if exists
        if ($user->getProfile()) {
            $form->get('firstName')->setData($user->getProfile()->getFirstName());
            $form->get('lastName')->setData($user->getProfile()->getLastName());
            $form->get('phone')->setData($user->getProfile()->getPhone());
            $form->get('description')->setData($user->getProfile()->getDescription());
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Update profile
                if ($user->getProfile()) {
                    $user->getProfile()->setFirstName($form->get('firstName')->getData());
                    $user->getProfile()->setLastName($form->get('lastName')->getData());
                    $user->getProfile()->setPhone($form->get('phone')->getData());
                    $user->getProfile()->setDescription($form->get('description')->getData());
                } else {
                    // Create profile if doesn't exist
                    $profile = new Profile();
                    $profile->setFirstName($form->get('firstName')->getData());
                    $profile->setLastName($form->get('lastName')->getData());
                    $profile->setPhone($form->get('phone')->getData());
                    $profile->setDescription($form->get('description')->getData());
                    $profile->setUser($user);
                    $entityManager->persist($profile);
                }

                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'User updated successfully.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating user: ' . $e->getMessage());
            }
        }

        return $this->render('Gestion_user/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            try {
                // Prevent deletion of current logged-in user
                if ($user->getId() === $this->getUser()->getId()) {
                    $this->addFlash('error', 'You cannot delete your own account.');
                    return $this->redirectToRoute('app_user_index');
                }

                $email = $user->getEmail();
                $entityManager->remove($user);
                $entityManager->flush();

                $this->addFlash('success', sprintf('User %s deleted successfully.', $email));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting user: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle-status-' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $status = $user->isActive() ? 'activated' : 'deactivated';
            $this->addFlash('success', sprintf('User account %s successfully.', $status));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_user_index');
    }
}
