<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class StoreException extends HttpException
{
    public const string CANNOT_UPLOAD_CORRECTLY = 'FRAMEWORK__EXTENSION_CANNOT_BE_UPLOADED_CORRECTLY';
    public const string EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED = 'FRAMEWORK__EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED';
    public const string MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__STORE_MISSING_REQUEST_PARAMETER';
    public const string INVALID_TYPE = 'FRAMEWORK__STORE_INVALID_TYPE';
    public const string PLUGIN_NOT_A_ZIP_FILE = 'FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE';

    public static function couldNotUploadExtensionCorrectly(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_UPLOAD_CORRECTLY,
            'Extension could not be uploaded correctly.'
        );
    }

    public static function extensionRuntimeExtensionManagementNotAllowed(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED,
            'Runtime extension management is disabled'
        );
    }

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function invalidType(string $expected, string $actual): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_TYPE,
            \sprintf('Expected collection element of type %s got %s', $expected, $actual)
        );
    }

    public static function pluginNotAZipFile(string $mimeType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PLUGIN_NOT_A_ZIP_FILE,
            'Extension is not a zip file. Got "{{ mimeType }}"',
            ['mimeType' => $mimeType]
        );
    }
}
