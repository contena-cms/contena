<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\ContentSystem\RenderingMode;

/**
 * @internal
 *
 * @final
 */
class FullResponseFactory extends AbstractResponseFactory
{
    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse
    {
        return new ContentRouteResponse($contentPage);
    }
}
