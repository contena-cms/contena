<?php declare(strict_types=1);

namespace Contena\Core\Framework\Uuid\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidUuidLengthException extends ContenaHttpException
{
    public function __construct(
        int $length,
        string $hex
    ) {
        parent::__construct(
            'UUID has a invalid length. 16 bytes expected, {{ length }} given. Hexadecimal reprensentation: {{ hex }}',
            ['length' => $length, 'hex' => $hex]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UUID_INVALID_LENGTH';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
