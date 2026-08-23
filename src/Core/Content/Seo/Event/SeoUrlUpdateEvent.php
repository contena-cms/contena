<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class SeoUrlUpdateEvent extends Event implements ContenaEvent
{
    /**
     * @param list<array<string, mixed>> $seoUrls
     */
    public function __construct(
        protected array $seoUrls,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }
}
