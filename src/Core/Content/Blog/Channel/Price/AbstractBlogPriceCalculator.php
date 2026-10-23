<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Price;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\Service\ResetInterface;

abstract class AbstractBlogPriceCalculator implements ResetInterface
{
    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    abstract public function getDecorated(): AbstractBlogPriceCalculator;

    /**
     * @param Entity[] $blogs
     */
    abstract public function calculate(iterable $blogs, ChannelContext $context): void;
}
