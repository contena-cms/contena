<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Message;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCompilerInterface;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class CompileThemeHandler
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeChannelRepository
     */
    public function __construct(
        private ThemeCompilerInterface $themeCompiler,
        private AbstractConfigLoader $configLoader,
        private FrontendPluginRegistry $extensionRegistry,
        private NotificationService $notificationService,
        private EntityRepository $channelRepository,
        private ThemeRuntimeConfigService $runtimeConfigService,
        private EntityRepository $themeChannelRepository,
        private EventDispatcherInterface $eventDispatcher,
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(CompileThemeMessage $message): void
    {
        $message->getContext()->addState(ThemeService::STATE_NO_QUEUE);

        // Skip before compiling if a newer switch already superseded this one (avoids wasted work).
        if ($message->isAssign() && $this->isSuperseded($message)) {
            return;
        }

        // On failure the exception propagates for Messenger to retry/dead-letter; the user is
        // notified once by CompileThemeFailedSubscriber, not per attempt.
        $themeConfig = $this->configLoader->load($message->getThemeId(), $message->getContext());
        $this->themeCompiler->compileTheme(
            $message->getChannelId(),
            $message->getThemeId(),
            $themeConfig,
            $this->extensionRegistry->getConfigurations(),
            $message->isWithAssets(),
            $message->getContext(),
        );

        $this->runtimeConfigService->refreshRuntimeConfig(
            $message->getThemeId(),
            $themeConfig,
            $message->getContext(),
            false,
            $this->extensionRegistry->getConfigurations(),
        );

        if ($message->isAssign()) {
            // Re-check after the compile: a switch requested meanwhile must win (compiled files
            // stay and are reused if reassigned).
            if ($this->isSuperseded($message)) {
                return;
            }

            $this->themeChannelRepository->upsert([[
                'themeId' => $message->getThemeId(),
                'channelId' => $message->getChannelId(),
            ]], $message->getContext());

            $this->eventDispatcher->dispatch(
                new ThemeAssignedEvent($message->getThemeId(), $message->getChannelId(), $message->getContext()),
            );
        }

        if ($message->getContext()->getScope() !== Context::USER_SCOPE) {
            return;
        }

        $channel = $this->channelRepository->search(
            new Criteria([$message->getChannelId()]),
            $message->getContext(),
        )->getEntities()->first();
        if (!$channel) {
            throw ThemeException::channelNotFound($message->getChannelId());
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'info',
                'message' => 'ct-theme-manager.detail.asyncCompilation.completed',
                'requiredPrivileges' => [],
            ],
            $message->getContext()
        );
    }

    private function isSuperseded(CompileThemeMessage $message): bool
    {
        $latestRequested = $this->systemConfigService->getString(
            ThemeService::CONFIG_KEY_PENDING_THEME,
            $message->getChannelId(),
        );

        return $latestRequested !== '' && $latestRequested !== $message->getThemeId();
    }
}
