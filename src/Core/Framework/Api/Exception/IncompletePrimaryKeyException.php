<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class IncompletePrimaryKeyException extends ContenaHttpException
{
    public function __construct(array $primaryKeyFields)
    {
        parent::__construct(
            'The primary key consists of {{ fieldCount }} fields. Please provide values for the following fields: {{ fieldsString }}',
            ['fieldCount' => \count($primaryKeyFields), 'fields' => $primaryKeyFields, 'fieldsString' => implode(', ', $primaryKeyFields)]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCOMPLETE_PRIMARY_KEY';
    }
}
