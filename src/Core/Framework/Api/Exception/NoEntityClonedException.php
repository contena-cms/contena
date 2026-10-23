<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class NoEntityClonedException extends ContenaHttpException
{
    public function __construct(
        string $entity,
        string $id
    ) {
        parent::__construct(
            'Could not clone entity {{ entity }} with id {{ id }}.',
            ['entity' => $entity, 'id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NO_ENTITIY_CLONED_ERROR';
    }
}
