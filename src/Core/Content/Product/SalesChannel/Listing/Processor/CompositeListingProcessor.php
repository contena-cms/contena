<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Listing\Processor;

use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
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

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->prepare($request, $criteria, $context);
        }
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($request, $result, $context);
        }
    }
}
