<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @extends Collection<BlogPurchaseLimit>
 */
class BlogPurchaseLimitCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return BlogPurchaseLimit::class;
    }
}
