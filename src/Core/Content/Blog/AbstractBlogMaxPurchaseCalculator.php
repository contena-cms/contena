<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractBlogMaxPurchaseCalculator
{
    abstract public function getDecorated(): AbstractBlogMaxPurchaseCalculator;

    abstract public function calculate(Entity $blog, ChannelContext $context): int;
}
