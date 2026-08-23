<?php declare(strict_types=1);

namespace Contena\Frontend\Framework;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class FrontendFrameworkException extends HttpException
{
    public const string APP_REQUEST_NOT_AVAILABLE = 'FRONTEND__APP_REQUEST_NOT_AVAILABLE';
    public const string CHANNEL_CONTEXT_OBJECT_NOT_FOUND = 'FRONTEND__CHANNEL_CONTEXT_OBJECT_NOT_FOUND';
    public const string CHANNEL_MAPPING_EXCEPTION = 'FRAMEWORK__INVALID_CHANNEL_MAPPING';
    public const string INVALID_ARGUMENT = 'FRONTEND__INVALID_ARGUMENT';
    public const string MEDIA_ILLEGAL_FILE_TYPE = 'FRONTEND__MEDIA_ILLEGAL_FILE_TYPE';
    public const string MEDIA_VALIDATOR_MISSING = 'FRONTEND__MEDIA_VALIDATOR_MISSING';

    public static function appRequestNotAvailable(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_REQUEST_NOT_AVAILABLE,
            'The "app.request" variable is not available.'
        );
    }

    public static function channelMappingException(string $url): self
    {
        return new Routing\Exception\ChannelMappingException($url);
    }

    public static function channelContextObjectNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_CONTEXT_OBJECT_NOT_FOUND,
            'Missing channel context object',
        );
    }

    public static function invalidArgument(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            $message,
        );
    }

    public static function fileTypeNotAllowed(string $mimeType, string $uploadType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_ILLEGAL_FILE_TYPE,
            'Type "{{ mimeType }}" of provided file is not allowed for {{ uploadType }}',
            ['mimeType' => $mimeType, 'uploadType' => $uploadType],
        );
    }

    public static function mediaValidatorMissing(string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_VALIDATOR_MISSING,
            'No validator for {{ type }} was found.',
            ['type' => $type],
        );
    }
}
