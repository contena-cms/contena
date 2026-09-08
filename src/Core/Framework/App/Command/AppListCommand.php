<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Command;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\Console\OutputFormatTrait;
use Contena\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list',
    description: 'Lists all apps',
)]
class AppListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @internal
     */
    public function __construct(private readonly AppStorage $appStorage)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        $this->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the app list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $filter = $input->getOption('filter');
        $apps = \is_string($filter) && $filter !== ''
            ? $this->appStorage->findAllWithNameOrLabel($filter, $context)
            : $this->appStorage->findAll($context);
        $apps->sort(static fn (AppEntity $a, AppEntity $b): int => $a->getName() <=> $b->getName());

        if ($format === self::FORMAT_JSON) {
            $output->write(json_encode($apps, \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $appTable = [];
        $active = 0;

        $io->title('Contena App Service');

        if ($filter) {
            $io->comment(\sprintf('Filtering for: %s', $filter));
        }

        foreach ($apps as $app) {
            $appTable[] = [
                $app->getName(),
                $app->getLabel() ? mb_strimwidth($app->getLabel(), 0, 40, '...') : '',
                $app->getVersion(),
                $app->getAuthor(),
                $app->isActive() ? 'Yes' : 'No',
            ];

            if ($app->isActive()) {
                ++$active;
            }
        }

        $io->table(
            ['App', 'Label', 'Version', 'Author', 'Active'],
            $appTable
        );

        $io->text(
            \sprintf(
                '%d apps, %d active',
                \count($appTable),
                $active
            )
        );

        return self::SUCCESS;
    }
}
