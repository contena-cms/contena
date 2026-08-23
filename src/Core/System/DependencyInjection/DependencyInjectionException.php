<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\HttpException;

/**
 * @codeCoverageIgnore
 */
class DependencyInjectionException extends HttpException
{
    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }
}
