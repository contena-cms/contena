<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Requirement\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
abstract class RequirementException extends ContenaHttpException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_FAILED_DEPENDENCY;
    }
}
