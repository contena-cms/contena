<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{isExpired: bool}>>
 */
class UserRecoveryIsExpiredResponse extends StoreApiResponse
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
