<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Search;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Frontend\Page\Page;

class SearchPage extends Page
{
    protected string $searchTerm;

    protected BlogListingResult $listing;

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }

    public function getListing(): BlogListingResult
    {
        return $this->listing;
    }

    public function setListing(BlogListingResult $listing): void
    {
        $this->listing = $listing;
    }
}
