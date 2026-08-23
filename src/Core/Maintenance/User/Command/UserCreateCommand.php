<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\User\Command;

use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:create',
    description: 'Creates a new user',
)]
class UserCreateCommand extends Command
{
    public function __construct(private readonly UserProvisioner $userProvisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the user')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Mark the user as admin')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the user')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'The user\'s display name')
            ->addOption('phoneNumber', null, InputOption::VALUE_REQUIRED, 'The user\'s phone number')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email for the user')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Assign the user to an ACL role by its technical code')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        $additionalData = [];
        $name = $input->getOption('name');
        if ($name) {
            $additionalData['name'] = $name;
        }

        $phoneNumber = $input->getOption('phoneNumber');
        if ($phoneNumber) {
            $additionalData['phoneNumber'] = $phoneNumber;
        }

        $email = $input->getOption('email');
        if ($email) {
            $additionalData['email'] = $email;
        }

        $roleCode = $input->getOption('role');
        if (\is_string($roleCode) && $roleCode !== '') {
            $additionalData['roleCode'] = $roleCode;
            $additionalData['admin'] = false;
        }

        if ($input->getOption('admin')) {
            $additionalData['admin'] = true;
        }

        $savedPassword = $this->userProvisioner->provision($username, $password, $additionalData);

        $message = \sprintf('User "%s" successfully created.', $username);
        if ($password === null) {
            $message .= \sprintf(' The newly generated password is: %s', $savedPassword);
            $io->warning('You didn\'t pass a password so a random one was generated. Please call "user:change-password" to set a new password.');
        }

        $io->success($message);

        return self::SUCCESS;
    }
}
