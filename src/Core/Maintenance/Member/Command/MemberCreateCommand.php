<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Member\Command;

use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Maintenance\Member\Service\MemberProvisioner;
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
    name: 'member:create',
    description: 'Creates a new member for the default Web channel',
)]
class MemberCreateCommand extends Command
{
    public function __construct(private readonly MemberProvisioner $memberProvisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email for the member')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the member')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'The member\'s display name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = $input->getOption('password');
        $password = \is_string($password) ? $password : null;
        $name = $input->getOption('name');
        $name = \is_string($name) ? $name : null;

        $savedPassword = $this->memberProvisioner->provision($email, $password, $name);

        $message = \sprintf('Member "%s" successfully created.', $email);
        if ($password === null) {
            $message .= \sprintf(' The newly generated password is: %s', $savedPassword);
            $io->warning('You didn\'t pass a password so a random one was generated.');
        }

        $io->success($message);

        return self::SUCCESS;
    }
}
