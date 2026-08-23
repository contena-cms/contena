<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context\Exception;

use Contena\Core\Framework\Api\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidContextSourceException extends ApiException
{
    public function __construct(
        string $expected,
        string $actual
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_INVALID_CONTEXT_SOURCE,
            'Expected ContextSource of "{{expected}}", but got "{{actual}}".',
            ['expected' => $expected, 'actual' => $actual]
        );
    }
}
