<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @extends ChannelApiResponse<Struct>
 */
class GenericChannelApiResponse extends ChannelApiResponse
{
    public function __construct(
        int $code,
        Struct $object,
    ) {
        $this->setStatusCode($code);

        parent::__construct($object);
    }
}
