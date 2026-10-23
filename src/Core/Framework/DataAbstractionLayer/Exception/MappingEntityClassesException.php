<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class MappingEntityClassesException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Mapping definition neither have entities nor collection.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MAPPING_ENTITY_DEFINITION_CLASSES';
    }
}
