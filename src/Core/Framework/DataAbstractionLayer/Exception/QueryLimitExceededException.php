<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class QueryLimitExceededException extends ContenaHttpException
{
    public function __construct(
        ?int $maxLimit,
        ?int $limit
    ) {
        parent::__construct(
            'The limit must be lower than or equal to MAX_LIMIT(={{ maxLimit }}). Given: {{ limit }}',
            ['maxLimit' => $maxLimit, 'limit' => $limit]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__QUERY_LIMIT_EXCEEDED';
    }
}
