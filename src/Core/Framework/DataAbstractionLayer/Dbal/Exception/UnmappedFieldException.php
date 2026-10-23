<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see DalUnmappedFieldException} instead
 */
class UnmappedFieldException extends ContenaHttpException
{
    public function __construct(
        string $field,
        EntityDefinition $definition
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        $fieldParts = explode('.', $field);
        $name = array_pop($fieldParts);

        parent::__construct(
            'Field "{{ field }}" in entity "{{ entity }}" was not found.',
            ['field' => $name, 'entity' => $definition->getEntityName()]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UNMAPPED_FIELD';
    }
}
