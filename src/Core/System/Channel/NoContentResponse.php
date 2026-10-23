<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;

/**
 * @extends ChannelApiResponse<ArrayStruct<array{}>>
 */
class NoContentResponse extends ChannelApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct());
        $this->setStatusCode(self::HTTP_NO_CONTENT);
    }
}
