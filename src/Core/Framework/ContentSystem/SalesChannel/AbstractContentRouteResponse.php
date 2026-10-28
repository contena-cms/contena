<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<Struct>
 */
#[Package('framework')]
abstract class AbstractContentRouteResponse extends StoreApiResponse
{
}
