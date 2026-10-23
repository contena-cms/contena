<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Event;

use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

class SeoUrlUpdateEvent extends Event implements ContenaEvent
{
    /**
     * @param list<array<string, mixed>> $seoUrls
     */
    public function __construct(
        protected array $seoUrls,
        private readonly ?Context $context = null
    ) {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public function getContext(): Context
    {
        // tag:v6.8.0 - Remove this null check, $context will be required
        if ($this->context === null) {
            throw SeoException::invalidEventData('No context provided. Pass $context to the constructor of ' . static::class);
        }

        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - Use getContext() instead, $context will be required in the constructor.
     */
    public function getNullableContext(): ?Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getNullableContext() is deprecated, use getContext() instead.');

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
