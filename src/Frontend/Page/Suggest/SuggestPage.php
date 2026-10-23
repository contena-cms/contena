<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Suggest;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Page\Page;

class SuggestPage extends Page
{
    protected string $searchTerm;

    /**
     * @var EntitySearchResult<BlogCollection>
     */
    protected EntitySearchResult $searchResult;

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    /**
     * @param EntitySearchResult<BlogCollection> $searchResult
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
