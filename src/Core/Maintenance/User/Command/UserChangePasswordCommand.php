<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\User\Command;

use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:change-password',
    description: 'Change the password of a user',
)]
class UserChangePasswordCommand extends Command
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New password for the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);
        $context = Context::createCLIContext();

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        if (!$password) {
            $passwordQuestion = new Question('Enter new password for user');
            $passwordQuestion->setValidator(Validation::createCallable(new NotBlank()));
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $user = $this->getUser($username, $context);
        if ($user === null) {
            $io->error(\sprintf('The user "%s" does not exist.', $username));

            return self::FAILURE;
        }

        $writeContext = $user->getTenantId() === null
            ? $context
            : Context::createTenantContext($user->getTenantId());

        $this->userRepository->update([
            [
                'id' => $user->getId(),
                'password' => $password,
            ],
        ], $writeContext);

        $io->success(\sprintf('The password of user "%s" has been changed successfully.', $username));

        return self::SUCCESS;
    }

    private function getUser(string $username, Context $context): ?UserEntity
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('username', $username));

        return $this->userRepository->search($criteria, $context)->getEntities()->first();
    }
}
