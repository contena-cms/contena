<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Service;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\System\Channel\ChannelEntity;

abstract class AbstractCategoryUrlGenerator
{
    abstract public function getDecorated(): AbstractCategoryUrlGenerator;

    abstract public function generate(CategoryEntity $category, ?ChannelEntity $channel): ?string;
}
