<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Contena\Core\Framework\Struct\Struct;

/**
 * Holds one rendered element directly in its vars, rather than inside an array. That is the placement the
 * resolver's direct-variable filter answers, and the one a page's elements array never produces.
 *
 * @internal
 */
class MockRenderedElementHolderStruct extends Struct
{
    public function __construct(protected RenderedElement $element)
    {
    }
}
