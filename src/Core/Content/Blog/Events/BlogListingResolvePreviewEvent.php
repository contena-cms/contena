<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

class BlogListingResolvePreviewEvent extends NestedEvent implements ContenaChannelEvent
{
    /**
     * @param array<string> $mapping
     */
    public function __construct(
        protected ChannelContext $context,
        protected Criteria $criteria,
        protected array $mapping,
        protected bool $hasOptionFilter
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @return array<string>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function replace(string $originalId, string $newId): void
    {
        if (!\array_key_exists($originalId, $this->mapping)) {
            throw BlogException::originalIdNotFound($originalId);
        }

        $this->mapping[$originalId] = $newId;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function hasOptionFilter(): bool
    {
        return $this->hasOptionFilter;
    }
}
