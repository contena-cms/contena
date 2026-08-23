<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Frontend\Pagelet\NavigationPagelet;

class HeaderPagelet extends NavigationPagelet
{
    public function __construct(
        Tree $navigation,
        protected LanguageCollection $languages,
    ) {
        parent::__construct($navigation);
    }

    public function getLanguages(): LanguageCollection
    {
        return $this->languages;
    }
}
