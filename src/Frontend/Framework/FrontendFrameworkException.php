<?php declare(strict_types=1);

namespace Contena\Frontend\Framework;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class FrontendFrameworkException extends HttpException
{
    public const APP_TEMPLATE_FILE_NOT_READABLE = 'FRONTEND__APP_TEMPLATE_NOT_READABLE';
    public const APP_REQUEST_NOT_AVAILABLE = 'FRONTEND__APP_REQUEST_NOT_AVAILABLE';
    public const CHANNEL_CONTEXT_OBJECT_NOT_FOUND = 'FRONTEND__CHANNEL_CONTEXT_OBJECT_NOT_FOUND';
    public const MEDIA_ILLEGAL_FILE_TYPE = 'FRONTEND__MEDIA_ILLEGAL_FILE_TYPE';
    public const INVALID_ARGUMENT = 'FRONTEND__INVALID_ARGUMENT';
    public const CHANNEL_MAPPING_EXCEPTION = 'FRAMEWORK__INVALID_CHANNEL_MAPPING';
    public const MEDIA_VALIDATOR_MISSING = 'FRONTEND__MEDIA_VALIDATOR_MISSING';

    public static function appTemplateFileNotReadable(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_TEMPLATE_FILE_NOT_READABLE,
            'Unable to read file from: {{ path }}.',
            ['path' => $path]
        );
    }

    public static function appRequestNotAvailable(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_REQUEST_NOT_AVAILABLE,
            'The "app.request" variable is not available.'
        );
    }

    public static function channelContextObjectNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_CONTEXT_OBJECT_NOT_FOUND,
            'Missing sales channel context object',
        );
    }

    public static function fileTypeNotAllowed(string $mimeType, string $uploadType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_ILLEGAL_FILE_TYPE,
            'Type "{{ mimeType }}" of provided file is not allowed for {{ uploadType }}',
            ['mimeType' => $mimeType, 'uploadType' => $uploadType]
        );
    }

    public static function invalidArgument(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            $message
        );
    }

    public static function channelMappingException(string $url): self
    {
        return new ChannelMappingException($url);
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function mediaValidatorMissing(string $type): self|MediaValidatorMissingException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new MediaValidatorMissingException($type);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_VALIDATOR_MISSING,
            'No validator for {{ type }} was found.',
            ['type' => $type],
        );
    }
}
