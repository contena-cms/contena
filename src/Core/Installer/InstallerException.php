<?php

declare(strict_types=1);

namespace Contena\Core\Installer;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class InstallerException extends HttpException
{
    final public const string INVALID_REQUIREMENT_CHECK = 'INSTALLER__INVALID_REQUIREMENT_CHECK';

    public static function invalidRequirementCheck(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_REQUIREMENT_CHECK,
            $message,
        );
    }
}
