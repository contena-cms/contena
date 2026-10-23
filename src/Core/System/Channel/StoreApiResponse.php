<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Struct\VariablesAccessTrait;

/**
 * @template TResponseObject of Struct
 */
class StoreApiResponse extends Response
{
    // allows the cache key finder to get access of all returned data to build the cache tags
    use VariablesAccessTrait;

    /**
     * @param TResponseObject $object
     */
    public function __construct(protected Struct $object)
    {
        parent::__construct();
    }

    /**
     * @return TResponseObject
     */
    public function getObject(): Struct
    {
        return $this->object;
    }
}
