<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class ExpectationFailedException extends ContenaHttpException
{
    /**
     * @param list<string> $fails
     */
    public function __construct(array $fails)
    {
        parent::__construct('API Expectations failed', ['fails' => $fails]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_EXPECTATION_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_EXPECTATION_FAILED;
    }
}
