<?php

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

$service = $container->get('App\Service\UserMailerService');

// Use reflection to get the private property
$reflection = new ReflectionClass($service);
$property = $reflection->getProperty('mailerDsn');
$property->setAccessible(true);
$mailerDsn = $property->getValue($service);

echo "MAILER_DSN in service: " . $mailerDsn . "\n";
echo "MAILER_DSN from env: " . ($_ENV['MAILER_DSN'] ?? 'NOT SET') . "\n";
