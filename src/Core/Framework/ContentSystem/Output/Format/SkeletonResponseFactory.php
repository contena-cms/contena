<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Channel\ContentSkeletonRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
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

    public function collectsValueIndex(): bool
    {
        return false;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentSkeletonRouteResponse(new ContentSkeletonPage(
            $result->reference->id,
            ContentSkeletonElement::fromRendered($result->tree),
            $result->reference->name,
            $result->reference->version,
        ));
    }
}
