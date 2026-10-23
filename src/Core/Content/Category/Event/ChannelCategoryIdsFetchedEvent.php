<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is triggered when category ids are fetched for a sales channel without using the DAL.
 */
final class ChannelCategoryIdsFetchedEvent extends Event implements ContenaEvent
{
    /**
     * @var array<string, string>
     */
    private array $categoryIds = [];

    /**
     * @param list<string> $categoryIds Category ids **must** be provided as hex strings
     */
    public function __construct(
        array $categoryIds,
        private readonly ChannelContext $context
    ) {
        foreach ($categoryIds as $categoryId) {
            $this->categoryIds[$categoryId] = $categoryId;
        }
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return \array_values($this->categoryIds);
    }

    /**
     * @param string $categoryId Category ID to check as hex string
     */
    public function hasId(string $categoryId): bool
    {
        return \array_key_exists($categoryId, $this->categoryIds);
    }

    /**
     * @param string $categoryId Category ID to remove from IDs as hex string
     */
    public function filterId(string $categoryId): void
    {
        unset($this->categoryIds[$categoryId]);
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
