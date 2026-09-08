<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
abstract class ActionButtonResponse extends Struct
{
    public function __construct(protected string $actionType)
    {
    }
}
