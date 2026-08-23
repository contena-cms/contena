<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class EntityRepositoryNotFoundException extends ContenaHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'EntityRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EntityRepository_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
