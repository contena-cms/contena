<?php declare(strict_types=1);

namespace Contena\Core\Framework\Feature\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class FeatureActiveException extends ContenaHttpException
{
    public function __construct(
        string $feature,
        ?\Throwable $previous = null
    ) {
        $message = \sprintf('This function can only be used with feature flag %s inactive', $feature);
        parent::__construct($message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FEATURE_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
