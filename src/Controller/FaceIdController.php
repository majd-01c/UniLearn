<?php

namespace App\Controller;

use App\Entity\FaceVerificationLog;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FaceIdController extends AbstractController
{
    private const MATCH_THRESHOLD = 0.55;
    private const MAX_VERIFY_ATTEMPTS = 3;
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private UserRepository $userRepository,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    // ─── Enrollment page ───────────────────────────────────────────────
    #[Route('/profile/face-enroll', name: 'face_enroll_page', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function enrollPage(): Response
    {
        return $this->render('Gestion_user/face/enroll.html.twig');
    }

    // ─── Save face descriptors (enrollment) ────────────────────────────
    #[Route('/api/face/enroll', name: 'face_enroll', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enroll(Request $request): JsonResponse
    {
        // CSRF check
        $token = $request->headers->get('X-CSRF-Token')
              ?? json_decode($request->getContent(), true)['_token'] ?? '';
        if (!$this->isCsrfTokenValid('face_enroll', $token)) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        // Consent checkbox
        if (empty($data['consent'])) {
            return $this->json(['error' => 'You must consent to biometric data storage.'], Response::HTTP_BAD_REQUEST);
        }

        $descriptors = $data['descriptors'] ?? null;
        if (!$this->validateDescriptors($descriptors)) {
            return $this->json(['error' => 'Invalid descriptor data. Expected array of float arrays.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        $user->setFaceDescriptors($descriptors);
        $user->setFaceEnabled(true);
        $user->setFaceEnrolledAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        // Log enrollment
        $log = new FaceVerificationLog();
        $log->setUser($user);
        $log->setAction('enroll');
        $log->setIpAddress($request->getClientIp());
        $this->entityManager->persist($log);

        $this->entityManager->flush();

        $this->logger->info('Face ID enrolled', ['user_id' => $user->getId()]);

        return $this->json(['success' => true, 'message' => 'Face ID has been enabled successfully.']);
    }

    // ─── Face verify page (2FA step after login) ───────────────────────
    #[Route('/face-verify', name: 'face_verify_page', methods: ['GET'])]
    public function verifyPage(Request $request): Response
    {
        $session = $request->getSession();

        // Only show if the user actually needs face verification
        if (!$session->get('needs_face_verification')) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('Gestion_user/face/verify.html.twig', [
            'attemptsLeft' => self::MAX_VERIFY_ATTEMPTS - (int) $session->get('face_verify_attempts', 0),
        ]);
    }

    // ─── Compare descriptor against stored (verification) ──────────────
    #[Route('/api/face/verify', name: 'face_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $session = $request->getSession();

        if (!$session->get('needs_face_verification')) {
            return $this->json(['error' => 'No face verification pending.'], Response::HTTP_BAD_REQUEST);
        }

        // Simple rate limiting via session
        $attempts = (int) $session->get('face_verify_attempts', 0);
        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            return $this->json([
                'match' => false,
                'attemptsLeft' => 0,
                'redirect' => $this->generateUrl('face_verify_skip'),
                'message' => 'Maximum attempts reached. Please use password-only login.',
            ]);
        }

        // CSRF check
        $token = $request->headers->get('X-CSRF-Token')
              ?? json_decode($request->getContent(), true)['_token'] ?? '';
        if (!$this->isCsrfTokenValid('face_verify', $token)) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->json(['error' => 'Invalid descriptor. Expected 128-float array.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $stored = $user->getFaceDescriptors();

        if (!$stored || !is_array($stored) || count($stored) === 0) {
            // Edge case: descriptors were cleared between login and verification
            $session->remove('needs_face_verification');
            $session->set('face_verified', true);
            return $this->json(['match' => true, 'redirect' => $this->generateUrl('app_home')]);
        }

        // Compute minimum Euclidean distance
        $minDistance = PHP_FLOAT_MAX;
        foreach ($stored as $storedDesc) {
            $dist = $this->euclideanDistance($descriptor, $storedDesc);
            if ($dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        $matched = $minDistance <= self::MATCH_THRESHOLD;

        // Log attempt
        $log = new FaceVerificationLog();
        $log->setUser($user);
        $log->setAction($matched ? 'verify_success' : 'verify_fail');
        $log->setDistance($minDistance);
        $log->setIpAddress($request->getClientIp());
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        if ($matched) {
            $session->remove('needs_face_verification');
            $session->remove('face_verify_attempts');
            $session->set('face_verified', true);

            $this->logger->info('Face ID verification succeeded', [
                'user_id' => $user->getId(),
                'distance' => $minDistance,
            ]);

            return $this->json([
                'match' => true,
                'redirect' => $session->get('_security.main.target_path', $this->generateUrl('app_home')),
            ]);
        }

        // Failed attempt
        $attempts++;
        $session->set('face_verify_attempts', $attempts);

        $this->logger->warning('Face ID verification failed', [
            'user_id' => $user->getId(),
            'distance' => $minDistance,
            'attempt' => $attempts,
        ]);

        $attemptsLeft = self::MAX_VERIFY_ATTEMPTS - $attempts;
        $response = [
            'match' => false,
            'attemptsLeft' => $attemptsLeft,
            'message' => $attemptsLeft > 0
                ? "Face not recognized. {$attemptsLeft} attempt(s) remaining."
                : 'Maximum attempts reached. Please use the skip option.',
        ];
        if ($attemptsLeft <= 0) {
            $response['redirect'] = $this->generateUrl('face_verify_skip');
        }

        return $this->json($response);
    }

    // ─── Skip Face ID (fallback) ───────────────────────────────────────
    #[Route('/face-verify/skip', name: 'face_verify_skip', methods: ['GET'])]
    public function skip(Request $request): Response
    {
        $session = $request->getSession();

        if ($session->get('needs_face_verification')) {
            $session->remove('needs_face_verification');
            $session->remove('face_verify_attempts');
            $session->set('face_verified', true);

            /** @var User|null $user */
            $user = $this->getUser();
            if ($user) {
                $log = new FaceVerificationLog();
                $log->setUser($user);
                $log->setAction('verify_skipped');
                $log->setIpAddress($request->getClientIp());
                $this->entityManager->persist($log);
                $this->entityManager->flush();
            }

            $this->addFlash('info', 'Face ID verification was skipped. You are now logged in.');
        }

        return $this->redirectToRoute('app_home');
    }

    // ─── Disable Face ID ────────────────────────────────────────────────
    #[Route('/api/face/disable', name: 'face_disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disable(Request $request): Response
    {
        // CSRF check
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('face_disable', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('profile_edit');
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setFaceEnabled(false);
        $user->setFaceDescriptors(null);
        $user->setFaceEnrolledAt(null);
        $user->setUpdatedAt(new \DateTimeImmutable());

        // Log
        $log = new FaceVerificationLog();
        $log->setUser($user);
        $log->setAction('disable');
        $log->setIpAddress($request->getClientIp());
        $this->entityManager->persist($log);

        $this->entityManager->flush();

        $this->logger->info('Face ID disabled', ['user_id' => $user->getId()]);
        $this->addFlash('success', 'Face ID has been disabled.');

        return $this->redirectToRoute('profile_edit');
    }

    // ─── Face Login page (standalone, no password needed) ──────────────
    #[Route('/login/face', name: 'face_login_page', methods: ['GET'])]
    public function faceLoginPage(): Response
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('Gestion_user/face/login.html.twig');
    }

    // ─── Face Login API (match against all enrolled users) ─────────────
    #[Route('/api/face/login', name: 'face_login', methods: ['POST'])]
    public function faceLogin(Request $request): JsonResponse
    {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->json(['error' => 'Already authenticated.'], Response::HTTP_BAD_REQUEST);
        }

        // CSRF check
        $token = $request->headers->get('X-CSRF-Token')
              ?? json_decode($request->getContent(), true)['_token'] ?? '';
        if (!$this->isCsrfTokenValid('face_login', $token)) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        // Simple rate limiting via session
        $session = $request->getSession();
        $attempts = (int) $session->get('face_login_attempts', 0);
        $lastAttemptTime = $session->get('face_login_last_attempt', 0);

        // Reset counter after 5 minutes
        if (time() - $lastAttemptTime > 300) {
            $attempts = 0;
        }

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            return $this->json([
                'match' => false,
                'message' => 'Too many attempts. Please wait a few minutes or use email/password login.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            return $this->json(['error' => 'Invalid descriptor. Expected 128-float array.'], Response::HTTP_BAD_REQUEST);
        }

        // Find all users with Face ID enabled
        $enrolledUsers = $this->userRepository->createQueryBuilder('u')
            ->where('u.faceEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();

        $bestMatch = null;
        $bestDistance = PHP_FLOAT_MAX;

        /** @var User $candidate */
        foreach ($enrolledUsers as $candidate) {
            $stored = $candidate->getFaceDescriptors();
            if (!$stored || !is_array($stored)) {
                continue;
            }
            // Check if account is active
            if (!$candidate->isActive()) {
                continue;
            }
            foreach ($stored as $storedDesc) {
                $dist = $this->euclideanDistance($descriptor, $storedDesc);
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $bestMatch = $candidate;
                }
            }
        }

        // Update rate limiting
        $attempts++;
        $session->set('face_login_attempts', $attempts);
        $session->set('face_login_last_attempt', time());

        $matched = $bestMatch !== null && $bestDistance <= self::MATCH_THRESHOLD;

        if ($matched) {
            // Log success
            $log = new FaceVerificationLog();
            $log->setUser($bestMatch);
            $log->setAction('face_login_success');
            $log->setDistance($bestDistance);
            $log->setIpAddress($request->getClientIp());
            $this->entityManager->persist($log);
            $this->entityManager->flush();

            // Programmatically authenticate the user
            $token = new UsernamePasswordToken($bestMatch, 'main', $bestMatch->getRoles());
            $this->tokenStorage->setToken($token);
            $session->set('_security_main', serialize($token));

            // Clear Face ID 2FA flag (they already proved it's them)
            $session->remove('needs_face_verification');
            $session->set('face_verified', true);

            // Reset login attempt counter
            $session->remove('face_login_attempts');

            $this->logger->info('Face ID login succeeded', [
                'user_id' => $bestMatch->getId(),
                'distance' => $bestDistance,
            ]);

            return $this->json([
                'match' => true,
                'redirect' => $this->generateUrl('app_home'),
                'message' => 'Welcome back, ' . ($bestMatch->getName() ?? $bestMatch->getEmail()) . '!',
            ]);
        }

        // Log failure (no specific user to log against, use a generic log)
        $this->logger->warning('Face ID login failed — no match', [
            'distance' => $bestDistance,
            'ip' => $request->getClientIp(),
        ]);

        $attemptsLeft = self::MAX_LOGIN_ATTEMPTS - $attempts;

        return $this->json([
            'match' => false,
            'attemptsLeft' => $attemptsLeft,
            'message' => $attemptsLeft > 0
                ? "Face not recognized. {$attemptsLeft} attempt(s) remaining."
                : 'Too many failed attempts. Please use email/password login.',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function validateDescriptors(mixed $descriptors): bool
    {
        if (!is_array($descriptors) || count($descriptors) < 1) {
            return false;
        }
        foreach ($descriptors as $desc) {
            if (!is_array($desc) || count($desc) !== 128) {
                return false;
            }
            foreach ($desc as $v) {
                if (!is_numeric($v)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $diff = ($a[$i] ?? 0) - ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}
