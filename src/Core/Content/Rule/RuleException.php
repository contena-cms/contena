<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Contena\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\HttpException;

class RuleException extends HttpException
{
    public static function conditionTypeNotFound(string $conditionName): ConditionTypeNotFound
    {
        return new ConditionTypeNotFound($conditionName);
    }

    public static function unsupportedCommandType(WriteCommand $command): UnsupportedCommandTypeException
    {
        return new UnsupportedCommandTypeException($command);
    }
}
