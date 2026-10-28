<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Cms;

use Contena\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class TestProductDetailCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'test';
    }


}
