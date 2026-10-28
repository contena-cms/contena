<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Message;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Storefront\Theme\Event\ThemeAssignedEvent;
use Contena\Storefront\Theme\Exception\ThemeException;
use Contena\Storefront\Theme\StorefrontPluginRegistry;
use Contena\Storefront\Theme\ThemeCompilerInterface;
use Contena\Storefront\Theme\ThemeRuntimeConfigService;
use Contena\Storefront\Theme\ThemeService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[AsMessageHandler]
final readonly class CompileThemeHandler
{
    /**
     * @param EntityRepository<SalesChannelCollection> $saleschannelRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeSalesChannelRepository
     */
    public function __construct(
        private ThemeCompilerInterface $themeCompiler,
        private AbstractConfigLoader $configLoader,
        private StorefrontPluginRegistry $extensionRegistry,
        private NotificationService $notificationService,
        private EntityRepository $saleschannelRepository,
        private ThemeRuntimeConfigService $runtimeConfigService,
        private EntityRepository $themeSalesChannelRepository,
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
            $message->getSalesChannelId(),
            $message->getThemeId(),
            $themeConfig,
            $this->extensionRegistry->getConfigurations(),
            $message->isWithAssets(),
            $message->getContext()
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

            $this->themeSalesChannelRepository->upsert([[
                'themeId' => $message->getThemeId(),
                'salesChannelId' => $message->getSalesChannelId(),
            ]], $message->getContext());

            $this->eventDispatcher->dispatch(
                new ThemeAssignedEvent($message->getThemeId(), $message->getSalesChannelId(), $message->getContext())
            );
        }

        if (!$message->isNotifyOnCompletion() || $message->getContext()->getScope() !== Context::USER_SCOPE) {
            return;
        }

        $salesChannel = $this->saleschannelRepository->search(
            new Criteria([$message->getSalesChannelId()]),
            $message->getContext()
        )->getEntities()->first();
        if (!$salesChannel) {
            throw ThemeException::salesChannelNotFound($message->getSalesChannelId());
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'info',
                'message' => 'sw-theme-manager.detail.asyncCompilation.completed',
                'requiredPrivileges' => [],
            ],
            $message->getContext()
        );
    }

    private function isSuperseded(CompileThemeMessage $message): bool
    {
        $latestRequested = $this->systemConfigService->getString(
            ThemeService::CONFIG_KEY_PENDING_THEME,
            $message->getSalesChannelId()
        );

        return $latestRequested !== '' && $latestRequested !== $message->getThemeId();
    }
}
