<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\DataAbstractionLayer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\Validator\Validation;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 *
 * @extends ChannelRepository<TEntityCollection>
 *
 * @phpstan-type ResultTypes EntitySearchResult<TEntityCollection>|AggregationResultCollection|mixed|TEntityCollection|IdSearchResult|array
 */
class StaticChannelRepository extends ChannelRepository
{
    /**
     * @param array<callable(Criteria, Context): (ResultTypes)|ResultTypes> $searches
     */
    public function __construct(
        private array $searches = [],
        ?EntityDefinition $definition = null,
    ) {
        if ($definition === null) {
            return;
        }

        try {
            $definition->getFields();
        } catch (\Throwable) {
            $registry = new StaticDefinitionInstanceRegistry(
                [$definition],
                Validation::createValidator(),
                new StaticEntityWriterGateway()
            );
            $definition->compile($registry);
        }
    }

    public function search(Criteria $criteria, ChannelContext $channelContext): EntitySearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            $result = $callable($criteria, $channelContext, $this);
        }

        if ($result instanceof EntitySearchResult) {
            return $result;
        }

        if (\is_array($result)) {
            $result = new EntityCollection($result);
        }

        if ($result instanceof EntityCollection) {
            /** @var TEntityCollection $result */
            return new EntitySearchResult(
                $result->count(),
                $result,
                null,
                $criteria,
                $channelContext->getContext(),
            );
        }

        if ($result instanceof AggregationResultCollection) {
            /** @var TEntityCollection $collection */
            $collection = new EntityCollection();

            return new EntitySearchResult(
                0,
                $collection,
                $result,
                $criteria,
                $channelContext->getContext(),
            );
        }

        throw new \RuntimeException('Invalid mock repository configuration');
    }

    public function searchIds(Criteria $criteria, ChannelContext $channelContext): IdSearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            $result = $callable($criteria, $channelContext);
        }

        if ($result instanceof IdSearchResult) {
            return $result;
        }

        if (!\is_array($result)) {
            throw new \RuntimeException('Invalid mock repository configuration');
        }

        if (\array_key_exists(0, $result) && \is_string($result[0])) {
            $result = \array_map(static fn (string $id) => ['primaryKey' => $id, 'data' => []], $result);
        }

        return new IdSearchResult(\count($result), $result, $criteria, $channelContext->getContext());
    }

    public function aggregate(Criteria $criteria, ChannelContext $channelContext): AggregationResultCollection
    {
        throw new \Exception('Aggregate is not implemented in static repository');
    }
}
