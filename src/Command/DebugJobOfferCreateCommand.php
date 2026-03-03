<?php

namespace App\Command;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferType;
use App\Service\JobOffer\JobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'debug:job-offer:create',
    description: 'Debug job offer creation by creating a test offer',
)]
class DebugJobOfferCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobOfferService $jobOfferService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Find or create a business partner user
            $partner = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'partner@unilearn.com']);
            
            if (!$partner) {
                $io->warning('No business partner found, creating one...');
                $partner = new User();
                $partner->setEmail('partner@unilearn.com');
                $partner->setRole('BUSINESS_PARTNER');
                $partner->setName('Test Business Partner');
                $partner->setIsActive(true);
                $partner->setIsVerified(true);
                $partner->setNeedsVerification(false);
                $partner->setPassword(password_hash('partner123', PASSWORD_DEFAULT));
                
                $this->entityManager->persist($partner);
                $this->entityManager->flush();
                $io->success('Business partner created!');
            }

            // Create a test job offer
            $offer = new JobOffer();
            $offer->setTitle('Debug Test Job Offer');
            $offer->setType(JobOfferType::JOB);
            $offer->setLocation('Test Location');
            $offer->setDescription('This is a test job offer to debug the creation process.');
            $offer->setRequirements('Test requirements');
            $offer->setRequiredSkills(['PHP', 'Symfony']);
            $offer->setMinExperienceYears(2);

            $io->info('Creating job offer...');
            $this->jobOfferService->createForPartner($offer, $partner);

            $io->success('✅ Job offer created successfully!');
            $io->info(sprintf('Job offer ID: %d', $offer->getId()));
            $io->info(sprintf('Title: %s', $offer->getTitle()));
            $io->info(sprintf('Status: %s', $offer->getStatus()->value));
            $io->info(sprintf('Partner: %s (%s)', $partner->getName(), $partner->getEmail()));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to create job offer: ' . $e->getMessage());
            $io->info('Exception trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}