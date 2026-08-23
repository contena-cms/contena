<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Channel;

use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;

/**
 * @final
 */
class ContentRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentPage $contentPage;

    public function __construct(
        ContentPage $contentPage,
    ) {
        parent::__construct($contentPage);
        $this->contentPage = $contentPage;
    }

    public function getContentPage(): ContentPage
    {
        return $this->contentPage;
    }
}
