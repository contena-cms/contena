<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidSortQueryException extends DataAbstractionLayerException
{
    public function __construct(?string $message = null, array $parameters = [])
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            DataAbstractionLayerException::INVALID_SORT_QUERY,
            $message ?? 'Invalid sort query',
            $parameters
        );
    }
}
