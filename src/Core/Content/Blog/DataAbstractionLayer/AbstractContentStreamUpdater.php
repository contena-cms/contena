<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;

abstract class AbstractContentStreamUpdater extends EntityIndexer
{
    /**
     * @param array<string> $ids
     */
    abstract public function updateBlogs(array $ids, Context $context): void;
}
