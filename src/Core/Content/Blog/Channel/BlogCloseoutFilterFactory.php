<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;

class BlogCloseoutFilterFactory extends AbstractBlogCloseoutFilterFactory
{
    public function getDecorated(): AbstractBlogCloseoutFilterFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(ChannelContext $context): MultiFilter
    {
        return new BlogCloseoutFilter();
    }
}
