<?php

namespace App\Command;

use App\Service\ForumAiAssistantService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:forum-ai-cleanup',
    description: 'Clean up expired AI suggestion cache entries'
)]
class ForumAiCleanupCommand extends Command
{
    public function __construct(
        private ForumAiAssistantService $aiAssistant
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Forum AI Cache Cleanup');
        $io->text('Removing expired AI suggestion cache entries...');

        try {
            $deletedCount = $this->aiAssistant->cleanupCache();
            
            if ($deletedCount > 0) {
                $io->success(sprintf('Successfully deleted %d expired cache entries.', $deletedCount));
            } else {
                $io->info('No expired cache entries found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
