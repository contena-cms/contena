<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class FirstRunWizardSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeChannelRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $themeChannelRepository,
        private readonly EntityRepository $channelRepository
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FirstRunWizardFinishedEvent::class => 'frwFinished',
        ];
    }

    public function frwFinished(FirstRunWizardFinishedEvent $event): void
    {
        // only run on open -> completed|failed transition
        if (!$event->getPreviousState()->isOpen() || $event->getState()->isOpen()) {
            return;
        }

        $context = $event->getContext();

        $this->themeLifecycleService->refreshThemes($context);

        $themeCriteria = new Criteria();
        $themeCriteria->addAssociation('channels');
        $themeCriteria->addFilter(new EqualsFilter('technicalName', 'Frontend'));

        $theme = $this->themeRepository->search($themeCriteria, $context)->getEntities()->first();
        if (!$theme) {
            throw new \RuntimeException('Default theme not found');
        }

        $themeChannels = $theme->getChannels();
        // only run if the themes are not already initialised
        if ($themeChannels && $themeChannels->count() > 0) {
            return;
        }

        $channelCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND));

        $channelIds = $this->channelRepository->search($channelCriteria, $context)->getEntities()->getIds();

        foreach ($channelIds as $id) {
            $this->themeService->compileTheme($id, $theme->getId(), $context);
            $this->themeChannelRepository->upsert([[
                'themeId' => $theme->getId(),
                'channelId' => $id,
            ]], $context);
        }
    }
}
