<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Contena\Core\Framework\Api\Context\SalesChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Can be removed as the tested class will be removed as well
 */
#[Package('framework')]
#[CoversClass(StoreApiRouteCacheKeyEvent::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class StoreApiRouteCacheKeyEventTest extends TestCase
{
    private SalesChannelContext $context;

    private Request $request;

    private StoreApiRouteCacheKeyEvent $defaultEvent;

    private SalesChannelEntity $salesChannelEntity;

    protected function setUp(): void
    {
        $this->request = new Request();
        $this->salesChannelEntity = new SalesChannelEntity();
        $this->salesChannelEntity->setId(Uuid::randomHex());
        $this->context = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $this->salesChannelEntity
        );

        $this->defaultEvent = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, null);
    }

    public function testGetPartsWillReturnConstructorValue(): void
    {
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $event = new StoreApiRouteCacheKeyEvent($parts, $this->request, $this->context, null);
        static::assertSame($parts, $event->getParts());
    }

    public function testSetPartsWillGetPartsReturnSetterValue(): void
    {
        static::assertSame([], $this->defaultEvent->getParts());
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->defaultEvent->setParts($parts);
        static::assertSame($parts, $this->defaultEvent->getParts());
    }

    public function testGetRequestWillReturnCorrectRequest(): void
    {
        static::assertSame($this->request, $this->defaultEvent->getRequest());
    }

    public function testGetCriteriaWithCriteriaWillReturnCriteria(): void
    {
        $criteria = new Criteria();
        $event = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, $criteria);
        static::assertSame($criteria, $event->getCriteria());
    }

    public function testGetCriteriaWithNullInCriteriaWillReturnNull(): void
    {
        static::assertNull($this->defaultEvent->getCriteria());
    }

    public function testGetSalesChannelIdWillReturnChannelIdFromGivenContext(): void
    {
        static::assertSame($this->salesChannelEntity->getId(), $this->defaultEvent->getSalesChannelId());
    }

    public function testDisableCachingWillDisableCache(): void
    {
        static::assertTrue($this->defaultEvent->shouldCache());
        $this->defaultEvent->disableCaching();
        static::assertFalse($this->defaultEvent->shouldCache());
    }
}
