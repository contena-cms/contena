<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog\QuickView;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Struct\Struct;

class MinimalQuickViewPage extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected BlogEntity $blog,
    ) {
    }

    public function getBlog(): BlogEntity
    {
        return $this->blog;
    }
}
