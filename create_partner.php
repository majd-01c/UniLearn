<?php

require __DIR__.'/vendor/autoload.php';

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();
$passwordHasher = $container->get(UserPasswordHasherInterface::class);

// Delete existing business partner if exists
$existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'partner@unilearn.com']);
if ($existingUser) {
    $entityManager->remove($existingUser);
    $entityManager->flush();
    echo "Removed existing business partner user\n";
}

// Create new business partner user
$partner = new User();
$partner->setEmail('partner@unilearn.com');
$partner->setRole('BUSINESS_PARTNER');  // This will become ROLE_BUSINESS_PARTNER
$partner->setName('Test Business Partner');
$partner->setIsActive(true);
$partner->setIsVerified(true);
$partner->setNeedsVerification(false);

// Hash the password
$hashedPassword = $passwordHasher->hashPassword($partner, 'partner123');
$partner->setPassword($hashedPassword);

$entityManager->persist($partner);
$entityManager->flush();

echo "✅ Business Partner user created successfully!\n";
echo "Email: partner@unilearn.com\n";
echo "Password: partner123\n";
echo "Role: BUSINESS_PARTNER (converts to ROLE_BUSINESS_PARTNER)\n";