<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;

abstract class AbstractBlogVariationBuilder
{
    abstract public function getDecorated(): AbstractBlogVariationBuilder;

    abstract public function build(Entity $blog): void;
}
