<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Mail;

use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MailThemeConfigSubscriber implements EventSubscriberInterface
{
    private const CHANNEL_CONTEXT = 'channelContext';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $channelContextFactory,
        private readonly MailThemeIdLoader $mailThemeIdLoader,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
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

        $context = $event->getContext();

        $options = [
            ChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
            ChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
        ];

        try {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $channelId,
                $options
            );
        } catch (NoContextDataException) {
            // Mail simulations can use generated sales-channel shells without persisted context data.
            // Rendering can continue with the existing template data in that case.
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

        $channelId = $channel?->getId();
        if ($channelId !== null && Uuid::isValid($channelId)) {
            return $channelId;
        }

        $templateChannel = $templateData['channel'] ?? null;
        if ($templateChannel instanceof ChannelEntity) {
            $channelId = $templateChannel->getId();
            if (Uuid::isValid($channelId)) {
                return $channelId;
            }
        }

        $channelId = $templateData['channelId'] ?? null;
        if (!\is_string($channelId) || !Uuid::isValid($channelId)) {
            return null;
        }

        return $channelId;
    }
}
