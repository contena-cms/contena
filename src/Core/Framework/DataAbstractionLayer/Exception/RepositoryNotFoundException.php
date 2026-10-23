<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class RepositoryNotFoundException extends ContenaHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct('Repository for entity "{{ entityName }}" does not exist.', ['entityName' => $entity]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__REPOSITORY_NOT_FOUND';
    }
}
