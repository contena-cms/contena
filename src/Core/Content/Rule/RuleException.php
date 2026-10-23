<?php

declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class RuleException extends HttpException
{
    public static function unsupportedCommandType(WriteCommand $command): HttpException
    {
        return new UnsupportedCommandTypeException($command);
    }
}
