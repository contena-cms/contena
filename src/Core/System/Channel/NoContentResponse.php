<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;

/**
 * @extends StoreApiResponse<ArrayStruct<array{}>>
 */
class NoContentResponse extends StoreApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct());
        $this->setStatusCode(self::HTTP_NO_CONTENT);
    }
}
