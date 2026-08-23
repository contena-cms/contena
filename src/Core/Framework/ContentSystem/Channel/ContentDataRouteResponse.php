<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Channel;

use Contena\Core\Framework\ContentSystem\Output\Struct\ContentDataPage;

/**
 * @final
 */
class ContentDataRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentDataPage $dataPage;

    public function __construct(
        ContentDataPage $dataPage,
    ) {
        parent::__construct($dataPage);
        $this->dataPage = $dataPage;
    }

    public function getContentDataPage(): ContentDataPage
    {
        return $this->dataPage;
    }
}
