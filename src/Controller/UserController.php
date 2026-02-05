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
        return $this->render('user/index.html.twig', [
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

            // Send welcome email with temporary password
            $userEmail = $user->getEmail();
            $logger->info('Attempting to send welcome email', ['email' => $userEmail]);
            
            if (empty($userEmail)) {
                $logger->error('Email is empty for new user');
                $this->addFlash('warning', sprintf(
                    'User created but email is empty! Temp password: %s',
                    $tempPassword
                ));
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }

            try {
                $loginUrl = $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $userMailer->sendWelcomeEmail($user, $tempPassword, $loginUrl);
                
                $logger->info('Welcome email sent successfully to: ' . $userEmail);
                
                $this->addFlash('success', sprintf(
                    'User created! Email sent to %s. Temp password: %s',
                    $userEmail,
                    $tempPassword
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

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
