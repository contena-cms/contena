<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class RuntimeFieldInCriteriaException extends ContenaHttpException
{
    public function __construct(string $field)
    {
        parent::__construct(
            'Field {{ field }} is a Runtime field and cannot be used in a criteria',
            ['field' => $field]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__RUNTIME_FIELD_IN_CRITERIA';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
