<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\AdminUser\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'admin_user:change-password',
    description: 'Change the password of a admin_user',
)]
class AdminUserChangePasswordCommand extends Command
{
    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(private readonly EntityRepository $adminUserRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New password for the admin_user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        if (!$password) {
            $passwordQuestion = new Question('Enter new password for admin_user');
            $passwordQuestion->setValidator(Validation::createCallable(new NotBlank()));
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $adminUserId = $this->getUserId($username, $context);
        if ($adminUserId === null) {
            $io->error(\sprintf('The admin user "%s" does not exist.', $username));

            return self::FAILURE;
        }

        $this->adminUserRepository->update([
            [
                'id' => $adminUserId,
                'password' => $password,
            ],
        ], $context);

        $io->success(\sprintf('The password of admin user "%s" has been changed successfully.', $username));

        return self::SUCCESS;
    }

    private function getUserId(string $username, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('username', $username));

        return $this->adminUserRepository->searchIds($criteria, $context)->firstId();
    }
}
