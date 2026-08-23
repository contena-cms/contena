<?php declare(strict_types=1);

namespace Contena\Core\Framework\Uuid;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

/**
 * @codeCoverageIgnore
 */
class UuidException extends HttpException
{
    public static function invalidUuid(string $uuid): ContenaHttpException
    {
        return new InvalidUuidException($uuid);
    }

    public static function invalidUuidLength(int $length, string $hex): ContenaHttpException
    {
        return new InvalidUuidLengthException($length, $hex);
    }
}
