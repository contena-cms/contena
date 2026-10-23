<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Garan;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{svg: string|null, nestedSvg: string|null}>>
 */
class GaranLabelRouteResponse extends StoreApiResponse
{
    public function __construct(?string $svg, ?string $nestedSvg = null)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'nestedSvg' => $nestedSvg], 'garan_label'));
    }
}
