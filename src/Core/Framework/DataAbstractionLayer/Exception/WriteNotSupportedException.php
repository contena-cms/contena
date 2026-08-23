<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class WriteNotSupportedException extends ContenaHttpException
{
    private readonly Field $field;

    public function __construct(Field $field)
    {
        parent::__construct(
            'Writing to ReadOnly field "{{ field }}" is not supported.',
            ['field' => $field::class]
        );

        $this->field = $field;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__WRITE_NOT_SUPPORTED';
    }
}
