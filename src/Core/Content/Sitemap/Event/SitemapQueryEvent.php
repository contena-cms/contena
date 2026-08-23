<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

final class SitemapQueryEvent extends Event implements GenericEvent, ContenaChannelEvent
{
    public function __construct(
        public readonly QueryBuilder $query,
        public readonly int $limit,
        public readonly ?int $offset,
        private readonly ChannelContext $channelContext,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }
}
