<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Contena\Core\System\Channel\Exception\NoContextDataException;
use Contena\Core\System\Member\Exception\MemberNotFoundByIdException;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

class ChannelException extends HttpException
{
    final public const CHANNEL_CONTEXT_PERMISSIONS_LOCKED = 'SYSTEM__CHANNEL_CONTEXT_PERMISSIONS_LOCKED';
    final public const CHANNEL_DOES_NOT_EXISTS = 'SYSTEM__CHANNEL_DOES_NOT_EXISTS';
    final public const MEMBER_GROUP_DOES_NOT_EXISTS = 'SYSTEM__MEMBER_GROUP_DOES_NOT_EXISTS';
    final public const LANGUAGE_NOT_FOUND = 'SYSTEM__LANGUAGE_NOT_FOUND';
    final public const CONTEXT_TOKEN_NOT_ACCESSIBLE = 'SYSTEM__CONTEXT_TOKEN_NOT_ACCESSIBLE';
    final public const string CONTEXT_TOKEN_SCOPE_MISMATCH = 'SYSTEM__CONTEXT_TOKEN_SCOPE_MISMATCH';
    final public const MEMBER_NOT_LOGGED_IN = 'SYSTEM__MEMBER_NOT_LOGGED_IN';
    final public const INVALID_TYPE = 'FRAMEWORK__INVALID_TYPE';
    final public const ENCODING_INVALID_STRUCT_EXCEPTION = 'SYSTEM__ENCODING_INVALID_STRUCT_EXCEPTION';
    final public const ENCODING_MISSING_AGGREGATION_EXCEPTION = 'SYSTEM__ENCODING_MISSING_AGGREGATION_EXCEPTION';
    final public const CHANNEL_MAPPING_INVALID_OPERATION = 'SYSTEM__CHANNEL_MAPPING_INVALID_OPERATION';
    final public const CHANNEL_FILE_INVALID_PATH = 'FRAMEWORK__CHANNEL_FILE_INVALID_PATH';
    final public const CHANNEL_FILE_INVALID_FILE_FAMILY = 'FRAMEWORK__CHANNEL_FILE_INVALID_FILE_FAMILY';
    final public const CHANNEL_FILE_MISSING_FILE_NAME = 'FRAMEWORK__CHANNEL_FILE_MISSING_FILE_NAME';
    final public const CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES = 'FRAMEWORK__CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES';
    final public const CHANNEL_FILE_NOT_FOUND = 'FRAMEWORK__CHANNEL_FILE_NOT_FOUND';
    final public const string CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY = 'FRAMEWORK__CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY';
    final public const NO_CONTEXT_DATA_EXCEPTION = 'SYSTEM__NO_CONTEXT_DATA';
    final public const LANGUAGE_INVALID_EXCEPTION = 'SYSTEM__LANGUAGE_INVALID';
    final public const CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION = 'SYSTEM__CHANNEL_LANGUAGE_NOT_AVAILABLE';
    final public const COUNTRY_INVALID_EXCEPTION = 'SYSTEM__COUNTRY_INVALID';
    final public const COUNTRY_NOT_FOUND = 'SYSTEM__COUNTRY_NOT_FOUND';
    private const string INVALID_UUID_MESSAGE_TEMPLATE = 'Provided %s is not a valid UUID';

    public static function contextPermissionsLocked(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_CONTEXT_PERMISSIONS_LOCKED,
            'Context permission in Channel context already locked.'
        );
    }

    public static function channelNotFound(string $channelId = ''): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CHANNEL_DOES_NOT_EXISTS,
            'Channel with id "{{ channelId }}" not found or not valid!.',
            ['channelId' => $channelId],
        );
    }

    public static function memberGroupNotFound(string $memberGroupId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEMBER_GROUP_DOES_NOT_EXISTS,
            self::$couldNotFindMessage,
            ['entity' => 'member group', 'field' => 'id', 'value' => $memberGroupId],
        );
    }

    public static function languageNotFound(string $languageId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId],
        );
    }

    public static function noContextData(string $channelId): self
    {
        return new NoContextDataException(
            Response::HTTP_PRECONDITION_FAILED,
            self::NO_CONTEXT_DATA_EXCEPTION,
            'No context data found for Channel "{{ channelId }}"',
            ['channelId' => $channelId]
        );
    }

    public static function invalidLanguageId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'language ID'),
        );
    }

    /**
     * @param array<string> $availableLanguages
     */
    public static function providedLanguageNotAvailable(string $languageId, array $availableLanguages): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION,
            \sprintf('Provided language "%s" is not in list of available languages: %s', $languageId, implode(', ', $availableLanguages)),
        );
    }

    public static function invalidCountryId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::COUNTRY_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'country ID'),
        );
    }

    public static function countryNotFound(string $countryId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::COUNTRY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'id', 'value' => $countryId]
        );
    }

    public static function contextTokenNotAccessible(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONTEXT_TOKEN_NOT_ACCESSIBLE,
            'The context token is not accessible in Twig rendering context, as the token should never be leaked in HTML content.',
        );
    }

    public static function contextTokenScopeMismatch(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONTEXT_TOKEN_SCOPE_MISMATCH,
            'The context token belongs to another channel.',
        );
    }

    public static function memberNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MEMBER_NOT_LOGGED_IN,
            'A logged-in member is required for this operation.',
        );
    }

    public static function memberNotFoundById(string $memberId): MemberNotFoundByIdException
    {
        return MemberException::memberNotFoundById($memberId);
    }

    public static function repositoryNotFound(string $entityName): ChannelRepositoryNotFoundException
    {
        return new ChannelRepositoryNotFoundException($entityName);
    }

    public static function invalidType(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_TYPE,
            $message,
        );
    }

    public static function encodingInvalidStructException(string $context): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_INVALID_STRUCT_EXCEPTION,
            'Invalid struct: "{{ context }}"',
            ['context' => $context],
        );
    }

    public static function encodingMissingAggregationException(int|string $key, int $index): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_MISSING_AGGREGATION_EXCEPTION,
            'Can not find encoded aggregation "{{ key }}" for data index "{{ index }}"',
            ['key' => $key, 'index' => $index],
        );
    }

    public static function invalidMappingOperation(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_MAPPING_INVALID_OPERATION,
            $message,
        );
    }

    public static function invalidChannelFilePath(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_FILE_INVALID_PATH,
            'The channel file path "{{ path }}" is invalid.',
            ['path' => $path]
        );
    }

    public static function invalidChannelFileFamily(string $fileFamily): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_FILE_INVALID_FILE_FAMILY,
            'The channel file family "{{ fileFamily }}" is invalid.',
            ['fileFamily' => $fileFamily]
        );
    }

    public static function missingChannelFileName(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_FILE_MISSING_FILE_NAME,
            'Parameter "fileName" must be a string.'
        );
    }

    public static function invalidChannelFileTemplateOverrides(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES,
            'Parameter "templateOverrides" must be an object.'
        );
    }

    public static function channelFileNotFound(string $fileFamily, string $fileName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CHANNEL_FILE_NOT_FOUND,
            'Could not find channel file "{{ fileFamily }}/{{ fileName }}".',
            ['fileFamily' => $fileFamily, 'fileName' => $fileName]
        );
    }

    public static function unexpectedCombinedPrimaryKey(string $entityName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY,
            'Expected a single field primary key for entity "{{ entityName }}", but got a combined primary key.',
            ['entityName' => $entityName],
        );
    }
}
