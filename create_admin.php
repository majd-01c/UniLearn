<?php

require __DIR__.'/vendor/autoload.php';

use App\Entity\User;
use App\Entity\Profile;

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

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

// Hash the password using PHP native bcrypt (compatible with Symfony's auto hasher)
$hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
$admin->setPassword($hashedPassword);

$entityManager->persist($admin);

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
