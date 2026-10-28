<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('checkout')]
#[AsCommand(
    name: 'store:login',
    description: 'Login to the store',
)]
class StoreLoginCommand extends Command
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $configService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('contenaId', 'i', InputOption::VALUE_REQUIRED, 'Contena ID')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User')
            ->addOption('host', 'g', InputOption::VALUE_OPTIONAL, 'License host')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createCLIContext();

        $host = $input->getOption('host');
        if ($host !== null && $host !== '') {
            $this->configService->set('core.store.licenseHost', $host, null, false);
        }

        $contenaId = $input->getOption('contenaId');
        $password = $input->getOption('password');
        $user = $input->getOption('user');

        if (!$password) {
            $passwordQuestion = new Question('Enter password');
            $passwordQuestion->setValidator(Validation::createCallable(new NotBlank(message: 'The password cannot be empty')));
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('user.username', $user));

        $userId = $this->userRepository->searchIds($criteria, $context)->firstId();

        if ($userId === null) {
            $io->error('User not found');

            return self::FAILURE;
        }

        $userContext = new Context(new AdminApiSource($userId));

        if ($contenaId === null || $password === null) {
            $io->error('Contena ID and password are required.');

            return self::FAILURE;
        }

        try {
            $this->storeClient->loginWithContenaId($contenaId, $password, $userContext);
        } catch (ClientException $exception) {
            $io->error(\sprintf('Store login failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $io->success('Successfully logged in.');

        return Command::SUCCESS;
    }
}
