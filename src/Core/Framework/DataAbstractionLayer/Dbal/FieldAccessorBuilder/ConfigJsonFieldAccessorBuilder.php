<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class ConfigJsonFieldAccessorBuilder extends JsonFieldAccessorBuilder
{
    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?string
    {
        if (!$field instanceof ConfigJsonField) {
            return null;
        }

        $jsonPath = preg_replace(
            '#^' . preg_quote($field->getPropertyName(), '#') . '#',
            '',
            $accessor
        );

        $accessor = $field->getPropertyName() . '.' . ConfigJsonField::STORAGE_KEY . $jsonPath;

        return parent::buildAccessor($root, $field, $context, $accessor);
    }
}
