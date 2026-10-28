<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class FullResponseFactory extends AbstractResponseFactory
{
    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function collectsValueIndex(): bool
    {
        return false;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentRouteResponse($result);
    }
}
