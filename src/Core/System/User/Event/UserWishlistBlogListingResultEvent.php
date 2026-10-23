<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class UserWishlistBlogListingResultEvent extends NestedEvent implements ContenaChannelEvent
{
    final public const EVENT_NAME = 'checkout.user.wishlist_listing_product_result';

    /**
     * @param EntitySearchResult<BlogCollection> $result
     */
    public function __construct(
        protected Request $request,
        protected EntitySearchResult $result,
        private ChannelContext $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }

    /**
     * @param EntitySearchResult<BlogCollection> $result
     */
    public function setResult(EntitySearchResult $result): void
    {
        $this->result = $result;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function setChannelContext(ChannelContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
