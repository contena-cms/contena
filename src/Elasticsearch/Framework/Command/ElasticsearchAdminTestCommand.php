<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Framework\Command;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
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
#[Package('inventory')]
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
            CustomerDefinition::ENTITY_NAME,
            CustomerGroupDefinition::ENTITY_NAME,
            LandingPageDefinition::ENTITY_NAME,
            ProductManufacturerDefinition::ENTITY_NAME,
            MediaDefinition::ENTITY_NAME,
            OrderDefinition::ENTITY_NAME,
            PaymentMethodDefinition::ENTITY_NAME,
            ProductDefinition::ENTITY_NAME,
            PromotionDefinition::ENTITY_NAME,
            PropertyGroupDefinition::ENTITY_NAME,
            SalesChannelDefinition::ENTITY_NAME,
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
