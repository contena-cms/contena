<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Subscriber;

use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CategorySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'channel.category.loaded' => 'channelCategoryLoaded',
        ];
    }

    /**
     * @param ChannelEntityLoadedEvent<ChannelCategoryEntity> $event
     */
    public function channelCategoryLoaded(ChannelEntityLoadedEvent $event): void
    {
        $channel = $event->getChannelContext()->getChannel();

        foreach ($event->getEntities() as $category) {
            $category->assign([
                'seoUrl' => $this->categoryUrlGenerator->generate($category, $channel),
            ]);
        }
    }
}
