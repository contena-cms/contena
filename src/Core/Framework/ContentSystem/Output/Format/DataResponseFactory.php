<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Channel\ContentDataRouteResponse;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\ContentSystem\RenderingMode;

/**
 * @internal
 *
 * @final
 */
class DataResponseFactory extends AbstractResponseFactory
{
    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly ConfigCanonicalizer $configCanonicalizer,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse
    {
        return new ContentDataRouteResponse($contentPage->getContentDataPage($this->configSerializerProvider, $this->configCanonicalizer));
    }
}
