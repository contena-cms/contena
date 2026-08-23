<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;

/**
 * @extends ChannelApiResponse<ArrayStruct<array{success: bool}>>
 */
class SuccessResponse extends ChannelApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct(['success' => true]));
    }
}
