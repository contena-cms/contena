<?php declare(strict_types=1);

namespace Contena\Core\Service\Permission;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum ConsentState: string
{
    case GRANTED = 'granted';
    case REVOKED = 'revoked';
}
