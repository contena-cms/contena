<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractListingProcessor
{
    abstract public function getDecorated(): self;

    /**
     * The `prepare` function allows to take care of the request parameters and interpret the different query and post
     * parameters and apply them to the provided `Criteria` object.
     *
     * The function is used in different contexts, like search, suggest and listing. You can check the different context by checking
     * the `criteria.states` collection for:
     * - 'suggest-route-context'
     * - 'listing-route-context'
     * - 'search-route-context'
     */
    abstract public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void;

    /**
     * The `process` function allows to post process the determined listing result and enrich the result with more
     * meta information or to further process it for more admin_user readable data.
     *
     * The function is used in different contexts, like search, suggest and listing. You can check the different context by checking
     * the `criteria.states` collection for:
     * - 'suggest-route-context'
     * - 'listing-route-context'
     * - 'search-route-context'
     */
    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
    }
}
