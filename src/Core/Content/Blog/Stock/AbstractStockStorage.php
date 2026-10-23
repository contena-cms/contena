<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractStockStorage
{
    abstract public function getDecorated(): self;

    /**
     * This method provides an extension point to augment the stock data when it is loaded.
     *
     * This method is called when loading blogs via:
     * * \Contena\Core\Content\Blog\Channel\Detail\AvailableCombinationLoader
     * * \Contena\Core\Content\Blog\Stock\LoadBlogStockSubscriber
     *
     * This data will be set directly on the blogs, overwriting their existing values. Furthermore, the keys specified below and any extra data will be added
     * as an array extension to the blog under the key `stock_data`.
     */
    abstract public function load(StockLoadRequest $stockRequest, ChannelContext $context): StockDataCollection;

    /**
     * This method should be used to update the stock value of a blog for a given order item change.
     *
     * @param list<StockAlteration> $changes
     */
    abstract public function alter(array $changes, Context $context): void;

    /**
     * This method is executed when a blog is created or updated. It can be used to perform some calculations such as update the `available` flag based on the new stock level.
     *
     * @param list<string> $blogIds
     */
    abstract public function index(array $blogIds, Context $context): void;
}
