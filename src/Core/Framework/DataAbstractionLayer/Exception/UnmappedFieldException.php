<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UnmappedFieldException extends DataAbstractionLayerException
{
    public function __construct(string $field, EntityDefinition $definition)
    {
        $fieldParts = explode('.', $field);
        $name = array_pop($fieldParts);

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            DataAbstractionLayerException::DBAL_UNMAPPED_FIELD,
            'Field "{{ field }}" in entity "{{ entity }}" was not found.',
            ['field' => $name, 'entity' => $definition->getEntityName()]
        );
    }
}
