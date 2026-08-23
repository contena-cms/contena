<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Framework\Console\OutputFormatTrait;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'channel:list',
    description: 'Lists all channels',
)]
class ChannelListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @var list<string>
     */
    private static array $headers = [
        'id',
        'Name',
        'Active',
        'Maintenance',
        'Default Language',
        'Languages',
        'Domains',
    ];

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(private readonly EntityRepository $channelRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addAssociations(['language', 'languages', 'domains']);
        $channels = $this->channelRepository->search($criteria, Context::createCLIContext())->getEntities();

        $data = [];
        foreach ($channels as $channel) {
            $language = $channel->getLanguage();
            $languages = $channel->getLanguages() ?? new LanguageCollection();
            $domains = $channel->getDomains() ?? new ChannelDomainCollection();

            $data[] = [
                $channel->getId(),
                $channel->getName() ?? 'n/a',
                $channel->getActive() ? 'active' : 'inactive',
                $channel->isMaintenance() ? 'on' : 'off',
                $language?->getName() ?? 'n/a',
                $languages->map(static fn (LanguageEntity $language) => $language->getName()),
                $domains->map(static fn (ChannelDomainEntity $domain) => $domain->getUrl()),
            ];
        }

        if ($format === self::FORMAT_JSON) {
            return $this->renderJson($output, $data);
        }

        return $this->renderTable($output, $data);
    }

    /**
     * @param list<list<string|array<string, string|null>>> $data
     */
    private function renderJson(OutputInterface $output, array $data): int
    {
        $json = [];

        foreach ($data as $row) {
            $jsonItem = [];
            foreach ($row as $item => $value) {
                $jsonItem[mb_strtolower((string) (self::$headers[$item] ?? $item))] = $value;
            }
            $json[] = $jsonItem;
        }

        $output->write(json_encode($json, \JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * @param list<list<string|array<string, string>>> $data
     */
    private function renderTable(OutputInterface $output, array $data): int
    {
        $table = new Table($output);
        $table->setHeaders(self::$headers);

        foreach ($data as $rowKey => $row) {
            foreach ($row as $columnKey => $column) {
                if (\is_array($column)) {
                    $data[$rowKey][$columnKey] = implode(', ', $column);
                }
            }
        }

        $table->addRows($data);
        $table->render();

        return self::SUCCESS;
    }
}
