<?php declare(strict_types=1);

namespace Contena\Core\Service\Permission;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
enum ConsentState: string
{
    case GRANTED = 'granted';
    case REVOKED = 'revoked';
}
