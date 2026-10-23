<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class DefinitionNotFoundException extends ContenaHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'Definition for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DEFINITION_NOT_FOUND';
    }
}
