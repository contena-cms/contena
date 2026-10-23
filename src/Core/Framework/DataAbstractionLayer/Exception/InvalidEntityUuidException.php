<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\Uuid\UuidException;
use Symfony\Component\HttpFoundation\Response;

class InvalidEntityUuidException extends DataAbstractionLayerException
{
    public function __construct(string $uuid)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_UUID',
            'Value is not a valid UUID: {{ uuid }}',
            ['uuid' => $uuid],
            UuidException::invalidUuid($uuid)
        );
    }
}
