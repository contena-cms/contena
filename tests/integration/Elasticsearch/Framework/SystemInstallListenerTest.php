<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Framework;

use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\SystemInstallCompletedEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class SystemInstallListenerTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    public function testInstallEventCreatesIndicesAndAllowsSearch(): void
    {
        $container = static::getContainer();
        $client = $container->get(Client::class);
        $helper = $container->get(ElasticsearchHelper::class);
        $definition = $container->get(ProductDefinition::class);

        $alias = $helper->getIndexName($definition);
        static::assertFalse($client->indices()->existsAlias(['name' => $alias]));

        $container->get(EventDispatcherInterface::class)
            ->dispatch(new SystemInstallCompletedEvent(Context::createCLIContext()));

        $this->refreshIndex();

        static::assertTrue($client->indices()->existsAlias(['name' => $alias]));

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        static::assertGreaterThanOrEqual(0, $result->getTotal());
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }
}
