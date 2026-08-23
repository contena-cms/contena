<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidAggregationQueryException extends DataAbstractionLayerException
{
    public function __construct(string $message)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_AGGREGATION_QUERY',
            '{{ message }}',
            ['message' => $message]
        );
    }
}
