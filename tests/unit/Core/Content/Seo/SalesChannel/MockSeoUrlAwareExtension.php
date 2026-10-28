<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SalesChannel;

use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class MockSeoUrlAwareExtension extends Struct
{
    /**
     * @var array<SalesChannelProductEntity>
     */
    protected array $searchResults = [];

    public function addSearchResult(SalesChannelProductEntity $entity): void
    {
        $this->searchResults[] = $entity;
    }
}
