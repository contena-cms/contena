<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractBlogCloseoutFilterFactory
{
    abstract public function getDecorated(): AbstractBlogCloseoutFilterFactory;

    abstract public function create(ChannelContext $context): MultiFilter;
}
