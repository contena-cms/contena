<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;

class ChannelContextStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractChannelContextFactory $factory)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ChannelContextAware) {
            return $stored;
        }

        $stored[MailAware::CHANNEL_ID] = $event->getChannelId();

        if ($event->getChannelContext()->getDomainId()) {
            $stored[ChannelContextAware::CHANNEL_DOMAIN_ID] = $event->getChannelContext()->getDomainId();
        }

        if ($event->getChannelContext()->getMemberId()) {
            $stored[ChannelContextAware::CHANNEL_MEMBER_ID] = $event->getChannelContext()->getMemberId();
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (
            !$storable->hasStore(MailAware::CHANNEL_ID)
            || $storable->hasStore(ChannelContextAware::CHANNEL_MEMBER_ID)
        ) {
            return;
        }

        $storable->lazy(
            ChannelContextAware::CHANNEL_CONTEXT,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?ChannelContext
    {
        $channelId = $storableFlow->getStore(MailAware::CHANNEL_ID);
        if (!\is_string($channelId)) {
            return null;
        }
        $domainId = $storableFlow->getStore(ChannelContextAware::CHANNEL_DOMAIN_ID);
        $context = $storableFlow->getContext();

        return $this->factory->create(
            Uuid::randomHex(),
            $channelId,
            [
                ChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                ChannelContextService::DOMAIN_ID => $domainId,
            ]
        );
    }
}
