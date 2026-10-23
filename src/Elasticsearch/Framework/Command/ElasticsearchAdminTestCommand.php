<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Framework\Command;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'es:admin:test',
    description: 'Allows you to test the admin search index',
)]
final class ElasticsearchAdminTestCommand extends Command
{
    private SymfonyStyle $io;

    /**
     * @internal
     */
    public function __construct(private readonly AdminSearcher $searcher)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $term = $input->getArgument('term');
        $entities = [
            CmsPageDefinition::ENTITY_NAME,
            UserDefinition::ENTITY_NAME,
            UserGroupDefinition::ENTITY_NAME,
            LandingPageDefinition::ENTITY_NAME,
            BlogManufacturerDefinition::ENTITY_NAME,
            MediaDefinition::ENTITY_NAME,
            OrderDefinition::ENTITY_NAME,
            PaymentMethodDefinition::ENTITY_NAME,
            BlogDefinition::ENTITY_NAME,
            PromotionDefinition::ENTITY_NAME,
            PropertyGroupDefinition::ENTITY_NAME,
            ChannelDefinition::ENTITY_NAME,
            ShippingMethodDefinition::ENTITY_NAME,
        ];

        $result = $this->searcher->search($term, $entities, Context::createCLIContext());

        $rows = [];
        foreach ($result as $data) {
            $rows[] = [$data['index'] ?? '', $data['indexer'] ?? '', $data['total']];
        }

        $this->io->table(['Index', 'Indexer', 'total'], $rows);

        return self::SUCCESS;
    }
}
