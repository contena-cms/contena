<?php declare(strict_types=1);

namespace Contena\Core\Framework\Util;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Util\Database\TableHelperException;
use Contena\Core\Framework\Util\Exception\Base64DecodingException;
use Contena\Core\Framework\Util\Exception\JsonDecodingException;
use Contena\Core\Framework\Util\Exception\UtilXmlParsingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UtilException extends HttpException
{
    public const string INVALID_JSON = 'UTIL_INVALID_JSON';
    public const string INVALID_JSON_NOT_LIST = 'UTIL_INVALID_JSON_NOT_LIST';
    public const string INVALID_JSON_NOT_ARRAY = 'UTIL_INVALID_JSON_NOT_ARRAY';
    public const string XML_PARSE_ERROR = 'UTIL__XML_PARSE_ERROR';
    public const string XML_ELEMENT_NOT_FOUND = 'UTIL__XML_ELEMENT_NOT_FOUND';
    public const string FILESYSTEM_FILE_NOT_FOUND = 'UTIL__FILESYSTEM_FILE_NOT_FOUND';
    public const string COULD_NOT_HASH_FILE = 'UTIL__COULD_NOT_HASH_FILE';
    public const string OPERATOR_NOT_SUPPORTED = 'UTIL__OPERATOR_NOT_SUPPORTED';
    public const string LENGTH_MUST_BE_GREATER_THAN_ZERO = 'UTIL__LENGTH_MUST_BE_GREATER_THAN_ZERO';
    public const string MIN_MUST_NOT_BE_GREATER_THAN_MAX = 'UTIL__MIN_MUST_NOT_BE_GREATER_THAN_MAX';
    public const string BASE64_DECODING_FAILED = 'UTIL__BASE64_DECODING_FAILED';
    public const string DB_TABLE_HELPER_EXCEPTION = 'UTIL__DB_TABLE_HELPER_EXCEPTION';

    public static function invalidJson(\JsonException $e): self
    {
        return new JsonDecodingException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON,
            'JSON is invalid',
            [],
            $e
        );
    }

    public static function invalidJsonNotList(): self
    {
        return new JsonDecodingException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON_NOT_LIST,
            'JSON cannot be decoded to a list'
        );
    }

    public static function invalidJsonNotArray(): JsonDecodingException
    {
        return new JsonDecodingException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON_NOT_ARRAY,
            'JSON cannot be decoded to an array'
        );
    }

    public static function xmlElementNotFound(string $element): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::XML_ELEMENT_NOT_FOUND,
            'Unable to locate element with the name "{{ element }}".',
            ['element' => $element]
        );
    }

    public static function xmlParsingException(string $file, string $message): self
    {
        return new UtilXmlParsingException($file, $message);
    }

    public static function cannotFindFileInFilesystem(string $file, string $filesystem): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FILESYSTEM_FILE_NOT_FOUND,
            'The file "{{ file }}" does not exist in the given filesystem "{{ filesystem }}"',
            ['file' => $file, 'filesystem' => $filesystem]
        );
    }

    public static function couldNotHashFile(string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COULD_NOT_HASH_FILE,
            'Could not generate hash for  "{{ file }}"',
            ['file' => $file]
        );
    }

    public static function lengthMustBeGreaterThanZero(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LENGTH_MUST_BE_GREATER_THAN_ZERO,
            'Length should be greater than 0'
        );
    }

    public static function minMustNotBeGreaterThanMax(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MIN_MUST_NOT_BE_GREATER_THAN_MAX,
            'The min parameter must be lower than or equal to max parameter'
        );
    }

    public static function operatorNotSupported(string $operator): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Operator "{{ operator }}" is not supported.',
            ['operator' => $operator]
        );
    }

    public static function base64DecodingFailed(): Base64DecodingException
    {
        return new Base64DecodingException(
            Response::HTTP_BAD_REQUEST,
            self::BASE64_DECODING_FAILED,
            'Failed to decode base64url data'
        );
    }

    public static function databaseTableHelperException(
        string $executedAction,
        \Throwable $previousException
    ): TableHelperException {
        return new TableHelperException($executedAction, $previousException);
    }
}
