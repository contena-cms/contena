<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class LoadBlogStockSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AbstractStockStorage $stockStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'channel.' . BlogEvents::BLOG_LOADED_EVENT => ['channelLoaded', 50],
            'channel.blog.partial_loaded' => ['channelLoaded', 50],
        ];
    }

    /**
     * @param ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event
     */
    public function channelLoaded(ChannelEntityLoadedEvent $event): void
    {
        $stocks = $this->stockStorage->load(
            new StockLoadRequest($event->getIds()),
            $event->getChannelContext()
        );

        foreach ($event->getEntities() as $blog) {
            $stock = $stocks->getStockForBlogId($blog->getId());

            if ($stock === null) {
                continue;
            }

            $blog->assign([
                // required stock data
                'stock' => $stock->stock,
                'available' => $stock->available,
                // optional stock data
                'minPurchase' => $stock->minPurchase ?? $blog->get('minPurchase'),
                'maxPurchase' => $stock->maxPurchase ?? $blog->get('maxPurchase'),
                'isCloseout' => $stock->isCloseout ?? $blog->get('isCloseout'),
            ]);

            // allow for arbitrary stock data to be added to the blog
            $blog->addExtension('stock_data', $stock);
        }
    }
}
