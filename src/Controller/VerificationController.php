<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\VerificationCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/verify')]
class VerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VerificationCodeGenerator $codeGenerator
    ) {
    }

    /**
     * Show verification form
     */
    #[Route('/email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verifyEmail(
        Request $request,
        \App\Service\UserMailerService $mailerService
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to verify your email.');
            return $this->redirectToRoute('app_login');
        }

        // Check if user is already verified
        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified.');
            return $this->redirectToRoute('app_home');
        }

        // Generate and send verification code on first access (GET request without existing code)
        if ($request->isMethod('GET') && !$user->getEmailVerificationCode()) {
            try {
                $verificationCode = $this->codeGenerator->generate();
                $expiryDate = $this->codeGenerator->getExpiryDate();
                
                $user->setEmailVerificationCode($verificationCode);
                $user->setCodeExpiryDate($expiryDate);
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                // Send verification code email
                $mailerService->sendNotificationEmail(
                    $user->getEmail(),
                    '🔐 Email Verification Code - UniLearn',
                    $this->getVerificationEmailContent($user->getProfile()?->getFirstName() ?? 'User', $verificationCode)
                );

                $this->addFlash('success', 'A verification code has been sent to your email.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to send verification code: ' . $e->getMessage());
            }
        }

        // Handle form submission
        if ($request->isMethod('POST')) {
            $code = $request->request->get('verification_code');
            
            if (empty($code)) {
                $this->addFlash('error', 'Please enter the verification code.');
                return $this->render('security/verify_email.html.twig', [
                    'user' => $user,
                ]);
            }

            // Check if code has expired
            if (!$this->codeGenerator->isCodeValid($user->getCodeExpiryDate())) {
                $this->addFlash('error', 'Verification code has expired. Please request a new one.');
                return $this->render('security/verify_email.html.twig', [
                    'user' => $user,
                    'expired' => true,
                ]);
            }

            // Validate code
            if ($code === $user->getEmailVerificationCode()) {
                $user->setIsVerified(true);
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
                $user->setNeedsVerification(false);
                $user->setEmailVerificationCode(null);
                $user->setCodeExpiryDate(null);
                $user->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();

                $this->addFlash('success', '✅ Your email has been verified successfully!');
                return $this->redirectToRoute('app_home');
            } else {
                $this->addFlash('error', 'Invalid verification code. Please try again.');
            }
        }

        return $this->render('security/verify_email.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Resend verification code
     */
    #[Route('/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resendCode(
        Request $request,
        UserRepository $userRepository,
        \App\Service\UserMailerService $mailerService,
        \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            $this->addFlash('error', 'You must be logged in.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified.');
            return $this->redirectToRoute('app_home');
        }

        try {
            // Generate new verification code
            $verificationCode = $this->codeGenerator->generate();
            $expiryDate = $this->codeGenerator->getExpiryDate();
            
            $user->setEmailVerificationCode($verificationCode);
            $user->setCodeExpiryDate($expiryDate);
            $user->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            // Send email with new code
            $loginUrl = $urlGenerator->generate('app_verify_email', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
            $mailerService->sendNotificationEmail(
                $user->getEmail(),
                '🔐 New Verification Code - UniLearn',
                $this->getResendEmailContent($user->getProfile()?->getFirstName() ?? 'User', $verificationCode)
            );

            $this->addFlash('success', 'A new verification code has been sent to your email.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to send verification code: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_verify_email');
    }

    private function getVerificationEmailContent(string $userName, string $verificationCode): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
        <h1 style="margin: 0;">🔐 Email Verification</h1>
    </div>
    <div style="background: white; padding: 30px;">
        <p>Hello <strong>{$userName}</strong>,</p>
        <p>Thank you for logging in! To activate your account and access all features, please verify your email address using the code below:</p>
        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 20px; margin: 20px 0;">
            <p style="font-size: 32px; text-align: center; letter-spacing: 8px; font-weight: bold; color: #0c5460; margin: 10px 0;">
                {$verificationCode}
            </p>
            <p style="color: #856404; text-align: center;"><strong>⏰ This code expires in 1 minute</strong></p>
        </div>
        <p>Please enter this code on the verification page to complete your account setup.</p>
        <p>If you didn't request this code, please ignore this email.</p>
        <p>Best regards,<br><strong>The UniLearn Team</strong></p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px;">
        <p>UniLearn Platform - Education Management System</p>
    </div>
</div>
HTML;
    }

    private function getResendEmailContent(string $userName, string $verificationCode): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
        <h1 style="margin: 0;">🔐 New Verification Code</h1>
    </div>
    <div style="background: white; padding: 30px;">
        <p>Hello <strong>{$userName}</strong>,</p>
        <p>You requested a new verification code. Here it is:</p>
        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 20px; margin: 20px 0;">
            <p style="font-size: 32px; text-align: center; letter-spacing: 8px; font-weight: bold; color: #0c5460; margin: 10px 0;">
                {$verificationCode}
            </p>
            <p style="color: #856404; text-align: center;"><strong>⏰ This code expires in 1 minute</strong></p>
        </div>
        <p>Please enter this code on the verification page to complete your account setup.</p>
        <p>Best regards,<br><strong>The UniLearn Team</strong></p>
    </div>
</div>
HTML;
    }
}
