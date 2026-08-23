<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
interface FieldAccessorBuilderInterface
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string;
}
