<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Mail;

use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MailThemeConfigSubscriber implements EventSubscriberInterface
{
    private const CHANNEL_CONTEXT = 'channelContext';

    public function __construct(
        private readonly ChannelContextServiceInterface $channelContextService,
        private readonly MailThemeIdLoader $mailThemeIdLoader,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailTemplateRenderContextEvent::class => 'addChannelContext',
        ];
    }

    public function addChannelContext(MailTemplateRenderContextEvent $event): void
    {
        $templateData = $event->getTemplateData();
        $channelId = $this->getChannelId($event->getChannel(), $templateData);
        if ($channelId === null) {
            return;
        }

        $themeId = $this->mailThemeIdLoader->load($channelId);
        if ($themeId !== null && !isset($templateData['themeId'])) {
            $event->addTemplateData('themeId', $themeId);
        }

        if (($templateData[self::CHANNEL_CONTEXT] ?? null) instanceof ChannelContext) {
            return;
        }

        try {
            $channelContext = $this->channelContextService->get(new ChannelContextServiceParameters(
                $channelId,
                Uuid::randomHex(),
                originalContext: $event->getContext(),
            ));
        } catch (\Throwable) {
            return;
        }

        $event->addTemplateData(self::CHANNEL_CONTEXT, $channelContext);
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function getChannelId(?ChannelEntity $channel, array $templateData): ?string
    {
        $channelContext = $templateData[self::CHANNEL_CONTEXT] ?? null;
        if ($channelContext instanceof ChannelContext) {
            return $channelContext->getChannelId();
        }

        $channelId = $channel?->getId() ?? ($templateData['channelId'] ?? null);
        if (!\is_string($channelId) || !Uuid::isValid($channelId)) {
            return null;
        }

        return $channelId;
    }
}
