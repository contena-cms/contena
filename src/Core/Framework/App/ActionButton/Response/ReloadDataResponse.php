<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
class ReloadDataResponse extends ActionButtonResponse
{
    final public const ACTION_TYPE = 'reload';

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
