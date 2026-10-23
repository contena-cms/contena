<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\AdminUser\Command;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'admin_user:create',
    description: 'Creates a new admin_user',
)]
class AdminUserCreateCommand extends Command
{
    public function __construct(private readonly AdminUserProvisioner $admin_userProvisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the admin_user')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Mark the admin_user as admin')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the admin_user')
            ->addOption('firstName', null, InputOption::VALUE_REQUIRED, 'The admin_user\'s firstname')
            ->addOption('lastName', null, InputOption::VALUE_REQUIRED, 'The admin_user\'s lastname')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email for the admin_user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        $additionalData = [];
        $lastName = $input->getOption('lastName');
        if ($lastName) {
            $additionalData['lastName'] = $lastName;
        }

        $firstName = $input->getOption('firstName');
        if ($firstName) {
            $additionalData['firstName'] = $firstName;
        }

        $email = $input->getOption('email');
        if ($email) {
            $additionalData['email'] = $email;
        }

        if ($input->getOption('admin')) {
            $additionalData['admin'] = true;
        }

        $savedPassword = $this->admin_userProvisioner->provision($username, $password, $additionalData);

        $message = \sprintf('AdminUser "%s" successfully created.', $username);
        if ($password === null) {
            $message .= \sprintf(' The newly generated password is: %s', $savedPassword);
            $io->warning('You didn\'t pass a password so a random one was generated. Please call "admin_user:change-password" to set a new password.');
        }

        $io->success($message);

        return self::SUCCESS;
    }
}
