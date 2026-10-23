<?php declare(strict_types=1);

namespace Contena\Core\System\Consent;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
enum ConsentStatus: string
{
    case UNSET = 'unset';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
    case DECLINED = 'declined';
}
