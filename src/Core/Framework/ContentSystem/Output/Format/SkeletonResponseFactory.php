<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Channel\ContentSkeletonRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\ContentSystem\RenderingMode;

/**
 * @internal
 *
 * @final
 */
class SkeletonResponseFactory extends AbstractResponseFactory
{
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::SKELETON;
    }

    public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse
    {
        return new ContentSkeletonRouteResponse($contentPage->getContentSkeletonPage());
    }
}
