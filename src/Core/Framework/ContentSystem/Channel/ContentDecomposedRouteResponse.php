<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Channel;

use Contena\Core\Framework\ContentSystem\Output\Struct\ContentDecomposedPage;

/**
 * @final
 */
class ContentDecomposedRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentDecomposedPage $contentDecomposedPage;

    public function __construct(
        ContentDecomposedPage $contentDecomposedPage,
    ) {
        parent::__construct($contentDecomposedPage);
        $this->contentDecomposedPage = $contentDecomposedPage;
    }

    public function getContentDecomposedPage(): ContentDecomposedPage
    {
        return $this->contentDecomposedPage;
    }
}
