<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel\Struct;

use Contena\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CrossSellingStruct extends Struct
{
    protected ?CrossSellingElementCollection $crossSellings = null;

    public function getCrossSellings(): ?CrossSellingElementCollection
    {
        return $this->crossSellings;
    }

    public function setCrossSellings(CrossSellingElementCollection $crossSellings): void
    {
        $this->crossSellings = $crossSellings;
    }

    public function getApiAlias(): string
    {
        return 'cms_cross_selling';
    }
}
