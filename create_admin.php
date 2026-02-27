<?php

require __DIR__.'/vendor/autoload.php';

use App\Entity\User;
use App\Entity\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();
$passwordHasher = $container->get(UserPasswordHasherInterface::class);

// Delete existing admin if exists
$existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@unilearn.com']);
if ($existingUser) {
    $entityManager->remove($existingUser);
    $entityManager->flush();
    echo "Removed existing admin user\n";
}

// Create new admin user
$admin = new User();
$admin->setEmail('admin@unilearn.com');
$admin->setRole('ADMIN');
$admin->setName('Admin User');
$admin->setIsActive(true);
$admin->setIsVerified(true);
$admin->setNeedsVerification(false);

// Hash the password
$hashedPassword = $passwordHasher->hashPassword($admin, 'admin123');
$admin->setPassword($hashedPassword);

$entityManager->persist($admin);
$entityManager->flush();

// Create profile for admin
$profile = new Profile();
$profile->setFirstName('Admin');
$profile->setLastName('User');
$profile->setUser($admin);
$entityManager->persist($profile);
$entityManager->flush();

echo "✅ Admin user created successfully!\n";
echo "Email: admin@unilearn.com\n";
echo "Password: admin123\n";
