<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\Exception\ThemeCompileException;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly ThemeLifecycleService $themeLifecycleService,
        private readonly EntityRepository $channelRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'updateFinished',
        ];
    }

    public function updateFinished(UpdatePostFinishEvent $event): void
    {
        $context = $event->getContext();

        if ($context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING)) {
            return;
        }

        $this->themeLifecycleService->refreshThemes($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->getAssociation('themes')->addFilter(new EqualsFilter('active', true));
        $alreadyCompiled = [];

        foreach ($this->channelRepository->search($criteria, $context)->getEntities() as $channel) {
            $themes = $channel->getExtensionOfType('themes', ThemeCollection::class);
            if (!$themes) {
                continue;
            }

            $failedThemes = [];
            foreach ($themes as $theme) {
                if (\in_array($theme->getId(), $alreadyCompiled, true)) {
                    continue;
                }

                try {
                    $alreadyCompiled += $this->themeService->compileThemeById($theme->getId(), $context);
                } catch (ThemeCompileException) {
                    $failedThemes[] = $theme->getName();
                    $alreadyCompiled[] = $theme->getId();
                }
            }

            if ($failedThemes !== []) {
                $event->appendPostUpdateMessage('Theme(s): ' . implode(', ', $failedThemes) . ' could not be recompiled.');
            }
        }
    }
}
