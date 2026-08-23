<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

/**
 * When a flow action implements this interface, it will be executed within a database transaction.
 */
interface TransactionalAction
{
}
