<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
abstract class AbstractFieldResolver
{
    abstract public function join(FieldResolverContext $context): string;
}
