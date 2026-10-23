<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Subscriber;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Contena\Core\Content\Blog\AbstractIsNewDetector;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\DBAL\Connection;

/**
 * Assigns the configured default CMS layout to blog entries that have none, so a freshly
 * created entry renders without the editor having to pick a layout first.
 *
 * @internal
 */
class BlogSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractIsNewDetector $isNewDetector,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection
    ) {
    }


    public static function getSubscribedEvents(): array
    {
        return [
            BlogEvents::BLOG_LOADED_EVENT => 'loaded',
            'blog.partial_loaded' => 'loaded',
            'channel.' . BlogEvents::BLOG_LOADED_EVENT => 'channelLoaded',
            'channel.blog.partial_loaded' => 'channelLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<BlogEntity|PartialEntity> $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $blog) {
            if (!$blog instanceof BlogEntity && !$blog instanceof PartialEntity) {
                continue;
            }

            $this->setDefaultLayout($blog);
        }
    }

    /**
     * @param ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event
     */
    public function channelLoaded(ChannelEntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $blog) {
            $this->setDefaultLayout($blog, $event->getChannelContext()->getChannelId());
        }
    }

    /**
     * @param Entity $blog - typehint as Entity because it could be a BlogEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $blog, ?string $channelId = null): void
    {
        if (!$blog->has('cmsPageId')) {
            return;
        }

        if ($blog->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $channelId);

        if (!$cmsPageId) {
            return;
        }

        $blog->assign(['cmsPageId' => $cmsPageId]);
    }
}
