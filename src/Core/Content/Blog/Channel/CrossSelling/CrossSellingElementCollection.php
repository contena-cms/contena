<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\CrossSelling;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CrossSellingElement>
 *
 * @codeCoverageIgnore
 */
class CrossSellingElementCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cross_selling_elements';
    }

    protected function getExpectedClass(): ?string
    {
        return CrossSellingElement::class;
    }
}
