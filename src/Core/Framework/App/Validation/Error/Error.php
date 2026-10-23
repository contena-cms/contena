<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
abstract class Error extends \Exception
{
    abstract public function getMessageKey(): string;
}
