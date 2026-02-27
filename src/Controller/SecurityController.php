<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordResetRequestType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use App\Service\UserMailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private PasswordResetService $passwordResetService,
        private UserMailerService $mailerService,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect to home if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('Gestion_user/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        // Redirect to home if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            
            /** @var User|null $user */
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Send reset email if user exists (for security, don't reveal if email exists)
            if ($user) {
                try {
                    // Generate reset token
                    $resetToken = $this->passwordResetService->generateResetToken($user);
                    
                    // Generate reset URL
                    $resetUrl = $this->generateUrl(
                        'app_reset_password',
                        ['token' => $resetToken->getToken()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                    
                    // Send reset email
                    $this->mailerService->sendPasswordResetLinkEmail($user, $resetUrl);
                } catch (\Exception $e) {
                    $this->logger->error('Forgot password request failed.', [
                        'email' => $email,
                        'exception' => $e,
                    ]);
                    $this->addFlash('error', 'An error occurred while processing your request. Please try again.');
                    return $this->redirectToRoute('app_forgot_password');
                }
            }

            // Show success message regardless (for security)
            $this->addFlash('success', 'If an account exists with this email, you will receive a password reset link shortly.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('Gestion_user/auth/forgot_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request): Response
    {
        // Redirect to home if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Validate reset token
        $resetToken = $this->passwordResetService->validateResetToken($token);
        if (!$resetToken) {
            $this->addFlash('error', 'This password reset link is invalid or has expired. Please request a new one.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $resetToken->getUser();
        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();

            try {
                // Reset password
                if ($this->passwordResetService->resetPassword($user, $newPassword)) {
                    // Mark token as used
                    $this->passwordResetService->markTokenAsUsed($resetToken);

                    $this->addFlash('success', 'Your password has been reset successfully. You can now log in with your new password.');
                    return $this->redirectToRoute('app_login');
                } else {
                    $this->addFlash('error', 'An error occurred while resetting your password. Please try again.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while resetting your password. Please try again.');
            }
        }

        return $this->render('Gestion_user/auth/reset_password.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
