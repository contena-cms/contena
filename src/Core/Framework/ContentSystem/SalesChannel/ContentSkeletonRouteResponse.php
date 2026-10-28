<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\SalesChannel;

use Contena\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSkeletonRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentSkeletonPage $skeletonPage;

    public function __construct(
        ContentSkeletonPage $skeletonPage,
    ) {
        parent::__construct($skeletonPage);
        $this->skeletonPage = $skeletonPage;
    }

    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return $this->skeletonPage;
    }
}
