<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class SitemapQueryEvent extends Event implements GenericEvent, ContenaSalesChannelEvent
{
    public function __construct(
        public readonly QueryBuilder $query,
        public readonly int $limit,
        public readonly ?int $offset,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
