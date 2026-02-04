<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
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

        // Remove existing admin if exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@unilearn.com']);
        
        if ($existingUser) {
            $this->entityManager->remove($existingUser);
            $this->entityManager->flush();
            $io->info('Removed existing admin user');
        }

        // Create new admin
        $admin = new User();
        $admin->setEmail('admin@unilearn.com');
        $admin->setRole('ADMIN');
        $admin->setName('Admin User');
        $admin->setIsActive(true);
        $admin->setIsVerified(true);
        $admin->setNeedsVerification(false);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Admin user created successfully!');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', 'admin@unilearn.com'],
                ['Password', 'admin123'],
                ['Role', 'ADMIN'],
            ]
        );

        return Command::SUCCESS;
    }
}
