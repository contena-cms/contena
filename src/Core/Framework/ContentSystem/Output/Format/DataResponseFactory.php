<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DataResponseFactory extends AbstractResponseFactory
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
        return true;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentDataRouteResponse($result);
    }
}
