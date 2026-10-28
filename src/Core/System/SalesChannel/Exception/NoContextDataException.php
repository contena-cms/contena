<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelException;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class NoContextDataException extends SalesChannelException
{
}
