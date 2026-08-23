<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<ArrayStruct<array{isExpired: bool}>>
 */
class MemberRecoveryIsExpiredResponse extends ChannelApiResponse
{
    public function __construct(bool $expired)
    {
        parent::__construct(new ArrayStruct(['isExpired' => $expired]));
    }

    public function isExpired(): bool
    {
        return $this->object->get('isExpired');
    }
}
