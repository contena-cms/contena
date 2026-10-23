<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Exception;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class UserDeletedException extends \Exception
{
    public function __construct(string $orderId)
    {
        $message = \sprintf('The user of the order with id %s has been deleted.', $orderId);

        parent::__construct($message);
    }
}
