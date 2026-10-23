<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Cms;

use Contena\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;

class CategoryNameCmsElementResolver extends TextCmsElementResolver
{
    public function getType(): string
    {
        return 'category-name';
    }
}
