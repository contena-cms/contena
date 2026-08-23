<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ChannelRepository::class)]
class ChannelRepositoryTest extends TestCase
{
    public function testSearchIdsIsInstrumented(): void
    {
        $operations = [];
        $this->repository($operations)->searchIds(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH_IDS], $operations);
    }

    public function testAggregateIsInstrumented(): void
    {
        $operations = [];
        $this->repository($operations)->aggregate(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_AGGREGATE], $operations);
    }

    public function testReadOnlySearchIsInstrumentedOnce(): void
    {
        $operations = [];
        $this->repository($operations)->search(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithIdLookupDoesNotMeasureNestedSearchIds(): void
    {
        $operations = [];
        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $this->repository($operations)->search($criteria, $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithAggregationDoesNotMeasureNestedAggregation(): void
    {
        $operations = [];
        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('agg', 'id'));
        $this->repository($operations)->search($criteria, $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    /**
     * @param list<string> $operations
     *
     * @return ChannelRepository<BlogCollection>
     */
    private function repository(array &$operations): ChannelRepository
    {
        $instrumentor = static::createStub(DalSearchInstrumentor::class);
        $instrumentor->method('measure')->willReturnCallback(
            function (string $operation, EntityDefinition $definition, Criteria $criteria, \Closure $callback) use (&$operations): mixed {
                $operations[] = $operation;

                return $callback();
            }
        );

        /** @var ChannelRepository<BlogCollection> $repository */
        $repository = new ChannelRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $instrumentor,
        );

        return $repository;
    }

    private function channelContext(): ChannelContext
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
