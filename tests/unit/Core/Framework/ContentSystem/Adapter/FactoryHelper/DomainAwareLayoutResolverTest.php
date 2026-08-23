<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutEntity;

/**
 * @internal
 */
#[CoversClass(DomainAwareLayoutResolver::class)]
class DomainAwareLayoutResolverTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('returns assignment when domain ID is known and repository returns entity')]
    public function testReturnsAssignmentWhenDomainIsKnown(): void
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($this->ids->get('layout'));

        $context = Generator::generateChannelContext();

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($entity): array {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(OrFilter::class, $filters[0]);

                static::assertSame(1, $criteria->getLimit());
                static::assertContains('contentLayout', array_keys($criteria->getAssociations()));

                return [$entity];
            },
        ]);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($entity, $result);
    }

    #[TestDox('returns assignment when domain ID is null using channel-only filter')]
    public function testReturnsAssignmentWhenDomainIsNull(): void
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($this->ids->get('layout'));

        $channel = new ChannelEntity();
        $channel->setId($this->ids->get('channel'));

        $context = static::createStub(ChannelContext::class);
        $context->method('getDomainId')->willReturn(null);
        $context->method('getChannel')->willReturn($channel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($entity): array {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(OrFilter::class, $filters[0]);

                static::assertSame(1, $criteria->getLimit());
                static::assertContains('contentLayout', array_keys($criteria->getAssociations()));

                return [$entity];
            },
        ]);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($entity, $result);
    }

    #[TestDox('returns most specific assignment when multiple candidates exist')]
    public function testReturnsMostSpecificAssignment(): void
    {
        $context = Generator::generateChannelContext();

        $domainSpecific = new HeaderContentLayoutEntity();
        $domainSpecific->setId($this->ids->get('domainAssignment'));
        $domainSpecific->setContentLayoutId('layout-domain');
        $domainSpecific->setDomainId($context->getDomainId());
        $domainSpecific->setChannelId($context->getChannel()->getId());

        $channelOnly = new HeaderContentLayoutEntity();
        $channelOnly->setId($this->ids->get('channelAssignment'));
        $channelOnly->setContentLayoutId('layout-channel');
        $channelOnly->setChannelId($context->getChannel()->getId());

        $repository = $this->createRepository($domainSpecific, $channelOnly);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($domainSpecific, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullWhenNoAssignmentExists(): void
    {
        $repository = $this->createRepository();

        $context = Generator::generateChannelContext();

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertNull($result);
    }

    /**
     * @return StaticEntityRepository<HeaderContentLayoutCollection>
     */
    private function createRepository(HeaderContentLayoutEntity ...$entities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$entities]);

        return $repository;
    }
}
