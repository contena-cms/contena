<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

final readonly class CompositeListingProcessor
{
    /**
     * @param iterable<AbstractListingProcessor> $processors
     *
     * @internal
     */
    public function __construct(private iterable $processors)
    {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->prepare($request, $criteria, $context);
        }
    }

    public function resolve(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->resolve($request, $criteria, $context);
        }
    }

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($request, $result, $context);
        }
    }
}
