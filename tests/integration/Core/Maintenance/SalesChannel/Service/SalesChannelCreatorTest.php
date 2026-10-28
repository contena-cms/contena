<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\SalesChannel\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Contena\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelCreatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private SalesChannelCreator $salesChannelCreator;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->salesChannelCreator = static::getContainer()->get(SalesChannelCreator::class);
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
    }

    public function testCreateSalesChannel(): void
    {
        $id = Uuid::randomHex();
        $this->salesChannelCreator->createSalesChannel($id, 'test', Defaults::SALES_CHANNEL_TYPE_API);

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($salesChannel);
        static::assertSame('test', $salesChannel->getName());
        static::assertSame(Defaults::SALES_CHANNEL_TYPE_API, $salesChannel->getTypeId());
    }
}
