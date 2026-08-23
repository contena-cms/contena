<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Suggest;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Frontend\Page\Page;

class SuggestPage extends Page
{
    protected string $searchTerm;

    protected BlogListingResult $searchResult;

    public function getSearchResult(): BlogListingResult
    {
        return $this->searchResult;
    }

    public function setSearchResult(BlogListingResult $searchResult): void
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
