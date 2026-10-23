<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\Struct;

/**
 * Mimics a CMS slot data struct holding a search result directly in its vars.
 *
 * @internal
 */
class MockNestedSearchResultStruct extends Struct
{
    /**
     * @param EntitySearchResult<BlogCollection> $listing
     */
    public function __construct(protected EntitySearchResult $listing)
    {
    }
}
