<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InAppPurchases\Response;

use Contena\Core\Framework\Struct\AssignArrayInterface;
use Contena\Core\Framework\Struct\AssignArrayTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class InAppPurchasesResponse implements AssignArrayInterface
{
    use AssignArrayTrait;

    /**
     * @var list<string>
     */
    public array $purchases = [];
}
