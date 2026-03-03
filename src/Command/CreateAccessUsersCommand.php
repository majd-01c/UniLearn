<?php

namespace App\Command;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-access-users',
    description: 'Create default access users (admin, 2 students, teacher, business partner)',
)]
class CreateAccessUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usersToCreate = [
            [
                'email' => 'admin@unilearn.com',
                'password' => 'admin123',
                'role' => 'ADMIN',
                'firstName' => 'Admin',
                'lastName' => 'User',
            ],
            [
                'email' => 'student1@unilearn.com',
                'password' => 'student123',
                'role' => 'STUDENT',
                'firstName' => 'Student',
                'lastName' => 'One',
            ],
            [
                'email' => 'student2@unilearn.com',
                'password' => 'student123',
                'role' => 'STUDENT',
                'firstName' => 'Student',
                'lastName' => 'Two',
            ],
            [
                'email' => 'teacher@unilearn.com',
                'password' => 'teacher123',
                'role' => 'TEACHER',
                'firstName' => 'Teacher',
                'lastName' => 'User',
            ],
            [
                'email' => 'partner@unilearn.com',
                'password' => 'partner123',
                'role' => 'BUSINESS_PARTNER',
                'firstName' => 'Business',
                'lastName' => 'Partner',
            ],
        ];

        foreach ($usersToCreate as $userData) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userData['email']]);

            if (!$user) {
                $user = new User();
                $user->setEmail($userData['email']);
                $this->entityManager->persist($user);
                $io->info(sprintf('Created user: %s', $userData['email']));
            } else {
                $io->info(sprintf('Updated existing user: %s', $userData['email']));
            }

            $user->setRole($userData['role']);
            $user->setName($userData['firstName'] . ' ' . $userData['lastName']);
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $user->setNeedsVerification(false);
            $user->setMustChangePassword(false);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $profile = $user->getProfile();
            if (!$profile) {
                $profile = new Profile();
                $profile->setUser($user);
                $this->entityManager->persist($profile);
            }

            $profile->setFirstName($userData['firstName']);
            $profile->setLastName($userData['lastName']);
        }

        $this->entityManager->flush();

        $io->success('Access users created/updated successfully.');
        $io->table(
            ['Email', 'Password', 'Role'],
            [
                ['admin@unilearn.com', 'admin123', 'ADMIN'],
                ['student1@unilearn.com', 'student123', 'STUDENT'],
                ['student2@unilearn.com', 'student123', 'STUDENT'],
                ['teacher@unilearn.com', 'teacher123', 'TEACHER'],
                ['partner@unilearn.com', 'partner123', 'BUSINESS_PARTNER'],
            ]
        );

        return Command::SUCCESS;
    }
}
