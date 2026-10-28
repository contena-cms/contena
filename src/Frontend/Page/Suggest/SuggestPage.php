<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Suggest;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;

#[Package('discovery')]
class SuggestPage extends Page
{
    protected string $searchTerm;

    /**
     * @var EntitySearchResult<ProductCollection>
     */
    protected EntitySearchResult $searchResult;

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $searchResult
     */
    public function setSearchResult(EntitySearchResult $searchResult): void
    {
        $this->searchResult = $searchResult;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }
}
