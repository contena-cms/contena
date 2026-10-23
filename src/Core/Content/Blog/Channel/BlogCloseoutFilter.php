<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

/**
 * @final
 */
class BlogCloseoutFilter extends NotFilter
{
    public function __construct()
    {
        parent::__construct(self::CONNECTION_AND, [
            new EqualsFilter('blog.isCloseout', true),
            new EqualsFilter('blog.available', false),
        ]);
    }
}
