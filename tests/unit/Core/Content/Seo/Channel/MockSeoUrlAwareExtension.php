<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class MockSeoUrlAwareExtension extends Struct
{
    /**
     * @var array<ChannelBlogEntity>
     */
    protected array $searchResults = [];

    public function addSearchResult(ChannelBlogEntity $entity): void
    {
        $this->searchResults[] = $entity;
    }
}
