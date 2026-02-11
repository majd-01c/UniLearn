<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Form\UserCreateType;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use App\Service\TempPasswordGenerator;
use App\Service\UserMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private TempPasswordGenerator $passwordGenerator,
        private UserMailerService $mailerService
    ) {
    }

    /**
     * List all users with search and filter
     */
    #[Route('', name: 'admin_users_list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): Response
    {
        $role = $request->query->get('role');
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        $queryBuilder = $userRepository->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p');

        // Filter by role
        if ($role) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        // Filter by status
        if ($status !== null && $status !== '') {
            $queryBuilder->andWhere('u.isActive = :status')
                ->setParameter('status', (bool) $status);
        }

        // Search by email or name
        if ($search) {
            $queryBuilder->andWhere(
                'u.email LIKE :search OR p.firstName LIKE :search OR p.lastName LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('u.createdAt', 'DESC');

        $users = $queryBuilder->getQuery()->getResult();

        return $this->render('Gestion_user/admin/users/list.html.twig', [
            'users' => $users,
            'currentRole' => $role,
            'currentStatus' => $status,
            'currentSearch' => $search,
        ]);
    }

    /**
     * Create new user
     */
    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Generate temporary password
                $tempPassword = $this->passwordGenerator->generate();
                
                // Hash and set password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $tempPassword);
                $user->setPassword($hashedPassword);
                $user->setMustChangePassword(true);
                $user->setTempPasswordGeneratedAt(new \DateTimeImmutable());

                // Create profile
                $profile = new Profile();
                $profile->setFirstName($form->get('firstName')->getData());
                $profile->setLastName($form->get('lastName')->getData());
                $profile->setPhone($form->get('phone')->getData());
                $profile->setDescription($form->get('description')->getData());
                $profile->setUser($user);

                // Persist entities
                $this->entityManager->persist($user);
                $this->entityManager->persist($profile);
                $this->entityManager->flush();

                // Send welcome email
                $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->mailerService->sendWelcomeEmail($user, $tempPassword, $loginUrl);

                $this->addFlash('success', sprintf(
                    'User account created successfully. Welcome email sent to %s',
                    $user->getEmail()
                ));

                return $this->redirectToRoute('admin_users_list');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating user: ' . $e->getMessage());
            }
        }

        return $this->render('Gestion_user/admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit existing user
     */
    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserEditType::class, $user);
        
        // Pre-fill profile data
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
                    $this->entityManager->persist($profile);
                }

                $user->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'User updated successfully.');
                return $this->redirectToRoute('admin_users_list');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating user: ' . $e->getMessage());
            }
        }

        return $this->render('Gestion_user/admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    /**
     * Toggle user active status
     */
    #[Route('/{id}/toggle-active', name: 'admin_users_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('toggle-active-' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $status = $user->isActive() ? 'activated' : 'deactivated';
            $this->addFlash('success', sprintf('User account %s successfully.', $status));
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_users_list');
    }

    /**
     * Reset user password (generate new temp password and send email)
     */
    #[Route('/{id}/reset-password', name: 'admin_users_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('reset-password-' . $user->getId(), $request->request->get('_token'))) {
            try {
                // Generate new temporary password
                $tempPassword = $this->passwordGenerator->generate();
                
                // Hash and set password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $tempPassword);
                $user->setPassword($hashedPassword);
                $user->setMustChangePassword(true);
                $user->setTempPasswordGeneratedAt(new \DateTimeImmutable());
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                // Send password reset email
                $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->mailerService->sendPasswordResetEmail($user, $tempPassword, $loginUrl);

                $this->addFlash('success', sprintf(
                    'Password reset successfully. New temporary password sent to %s',
                    $user->getEmail()
                ));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error resetting password: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_users_list');
    }
}
