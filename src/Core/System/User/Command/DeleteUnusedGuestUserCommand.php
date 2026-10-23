<?php declare(strict_types=1);

namespace Contena\Core\System\User\Command;

use Contena\Core\System\User\DeleteUnusedGuestUserService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:delete-unused-guests',
    description: 'Delete unused guest users',
)]
class DeleteUnusedGuestUsersCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly DeleteUnusedGuestUserService $deleteUnusedGuestUserService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createCLIContext();

        $count = $this->deleteUnusedGuestUserService->countUnusedUsers($context);

        if ($count === 0) {
            $io->comment('No unused guest users found.');

            return self::SUCCESS;
        }

        $confirm = $io->confirm(
            \sprintf('Are you sure that you want to delete %d guest users?', $count),
            false
        );

        if (!$confirm) {
            $io->caution('Aborting due to admin_user input.');

            return self::SUCCESS;
        }

        $progressBar = $io->createProgressBar($count);

        do {
            $ids = $this->deleteUnusedGuestUserService->deleteUnusedUsers($context);
            $progressBar->advance(\count($ids));
        } while ($ids);

        $progressBar->finish();

        $io->success(\sprintf('Successfully deleted %d guest users.', $count));

        return self::SUCCESS;
    }
}
