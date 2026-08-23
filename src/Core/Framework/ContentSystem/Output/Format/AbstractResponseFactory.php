<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\ContentSystem\RenderingMode;

/**
 * @internal
 */
abstract class AbstractResponseFactory
{
    abstract public function getRenderingMode(): RenderingMode;

    abstract public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse;
}
