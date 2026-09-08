<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Url;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
enum VerificationStatus
{
    case PASS;
    case HARD_FAIL;
    case SOFT_FAIL;
}
