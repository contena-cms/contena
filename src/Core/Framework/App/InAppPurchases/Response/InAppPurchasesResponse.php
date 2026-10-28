<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InAppPurchases\Response;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\AssignArrayInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InAppPurchasesResponse implements AssignArrayInterface
{
    use AssignArrayTrait;

    /**
     * @var list<string>
     */
    public array $purchases = [];
}
