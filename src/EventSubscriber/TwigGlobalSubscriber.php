<?php

namespace App\EventSubscriber;

use App\Enum\JobOfferStatus;
use App\Repository\JobOfferRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TwigGlobalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly JobOfferRepository $jobOfferRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Add pending job offers count to Twig globals
        $pendingCount = $this->jobOfferRepository->countByStatus(JobOfferStatus::PENDING);
        $this->twig->addGlobal('pendingJobOffersCount', $pendingCount);
    }
}
