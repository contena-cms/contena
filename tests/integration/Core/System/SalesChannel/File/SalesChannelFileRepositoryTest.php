<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SalesChannel\File;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItStoresTemplateOverridesAsSalesChannelScopedConfiguration(): void
    {
        $id = Uuid::randomHex();

        $repository = $this->getSalesChannelFileRepository();
        $repository->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'merchant override',
                    'Ucp' => 'plugin override',
                ],
            ],
        ], Context::createDefaultContext());

        $entity = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(SalesChannelFileEntity::class, $entity);
        static::assertSame(TestDefaults::SALES_CHANNEL, $entity->getSalesChannelId());
        static::assertSame('agentic', $entity->getFileFamily());
        static::assertSame('llms.txt', $entity->getFileName());
        static::assertTrue($entity->isEnabled());

        $templateOverrides = $entity->getTemplateOverrides();
        ksort($templateOverrides);

        static::assertSame([
            'Framework' => 'merchant override',
            'Ucp' => 'plugin override',
        ], $templateOverrides);
    }

    public function testSalesChannelAssociationLoadsFiles(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelFileRepository()->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'agents.md',
                'enabled' => false,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $criteria = (new Criteria([TestDefaults::SALES_CHANNEL]))->addAssociation('salesChannelFiles');
        $salesChannel = $this->getSalesChannelRepository()->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        static::assertNotNull($salesChannel->getSalesChannelFiles());
        static::assertTrue($salesChannel->getSalesChannelFiles()->has($id));
    }

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }

    /**
     * @return EntityRepository<SalesChannelCollection>
     */
    private function getSalesChannelRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel.repository');
    }
}
