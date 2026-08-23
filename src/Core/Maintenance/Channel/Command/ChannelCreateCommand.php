<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(name: 'channel:create', description: 'Creates a new channel')]
class ChannelCreateCommand extends Command
{
    public function __construct(private readonly ChannelCreator $channelCreator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id for the channel', Uuid::randomHex())
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for the application')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Default language', Defaults::LANGUAGE_SYSTEM)
            ->addOption('countryId', null, InputOption::VALUE_REQUIRED, 'Default country')
            ->addOption('typeId', null, InputOption::VALUE_OPTIONAL, 'Channel type id')
            ->addOption('memberGroupId', null, InputOption::VALUE_REQUIRED, 'Default member group')
            ->addOption('navigationCategoryId', null, InputOption::VALUE_REQUIRED, 'Default navigation category');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $accessKey = $this->channelCreator->createChannel(
                $input->getOption('id'),
                $input->getOption('name') ?? 'API',
                $input->getOption('typeId') ?? $this->getTypeId(),
                $input->getOption('languageId'),
                $input->getOption('countryId'),
                $input->getOption('memberGroupId'),
                $input->getOption('navigationCategoryId'),
                null,
                null,
                $this->getChannelConfiguration($input, $output)
            );

            $io->success('Channel has been created successfully.');
        } catch (WriteException $exception) {
            $io->error('Something went wrong.');
            $messages = [];
            foreach ($exception->getExceptions() as $error) {
                if ($error instanceof WriteConstraintViolationException) {
                    foreach ($error->getViolations() as $violation) {
                        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                }
            }

            $io->listing($messages);

            return self::SUCCESS;
        }

        $io->text('Access tokens:');
        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);
        $table->addRows([['Access key', $accessKey]]);
        $table->render();

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        return [];
    }

    protected function getTypeId(): string
    {
        return Defaults::CHANNEL_TYPE_API;
    }
}
