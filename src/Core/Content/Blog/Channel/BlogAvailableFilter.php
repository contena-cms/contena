<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

/**
 * @final
 */
class BlogAvailableFilter extends MultiFilter
{
    public function __construct(
        private readonly string $channelId,
        private readonly int $visibility = BlogVisibilityDefinition::VISIBILITY_ALL
    ) {
        parent::__construct(
            self::CONNECTION_AND,
            [
                new RangeFilter('blog.visibilities.visibility', [RangeFilter::GTE => $visibility]),
                new EqualsFilter('blog.visibilities.channelId', $channelId),
                new EqualsFilter('blog.active', true),
            ]
        );
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }
}
