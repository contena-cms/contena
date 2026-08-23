<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UnusedMediaSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly ThemeService $themeService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        $context = Context::createDefaultContext();
        $allThemeIds = $this->themeRepository->searchIds(new Criteria(), $context)->getIds();
        $mediaIds = [];

        foreach ($allThemeIds as $themeId) {
            $config = $this->themeService->getPlainThemeConfiguration($themeId, $context);
            foreach ($config['fields'] ?? [] as $data) {
                if (($data['type'] ?? null) === 'media' && ($data['value'] ?? null) && Uuid::isValid($data['value'])) {
                    $mediaIds[] = $data['value'];
                }
            }
        }

        $event->markAsUsed(array_unique($mediaIds));
    }
}
