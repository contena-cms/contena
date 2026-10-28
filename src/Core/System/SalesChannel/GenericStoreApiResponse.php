<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @extends StoreApiResponse<Struct>
 */
#[Package('framework')]
class GenericStoreApiResponse extends StoreApiResponse
{
    public function __construct(
        int $code,
        Struct $object,
    ) {
        $this->setStatusCode($code);

        parent::__construct($object);
    }
}
