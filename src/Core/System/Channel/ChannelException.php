<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Mcp\Exception\ResourceNotFoundException;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\System\User\Exception\UserNotFoundByIdException;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Payment\PaymentException;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\System\Channel\Exception\NoContextDataException;
use Symfony\Component\HttpFoundation\Response;

class ChannelException extends HttpException
{
    final public const CHANNEL_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CHANNEL_DOES_NOT_EXISTS';
    final public const LANGUAGE_INVALID_EXCEPTION = 'SYSTEM__LANGUAGE_INVALID_EXCEPTION';
    final public const COUNTRY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_DOES_NOT_EXISTS_EXCEPTION';
    final public const CURRENCY_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CURRENCY_DOES_NOT_EXISTS_EXCEPTION';
    final public const COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION';
    final public const TAX_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__TAX_DOES_NOT_EXISTS_EXCEPTION';
    final public const CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION';
    final public const SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION';
    final public const CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION = 'SYSTEM__CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION';
    final public const NO_CONTEXT_DATA_EXCEPTION = 'SYSTEM__NO_CONTEXT_DATA_EXCEPTION';
    final public const LANGUAGE_NOT_FOUND = 'SYSTEM__LANGUAGE_NOT_FOUND';
    final public const CHANNEL_DOMAIN_IN_USE = 'SYSTEM__CHANNEL_DOMAIN_IN_USE';
    final public const CRITERIA_TOO_MANY_NESTED_CRITERIA = 'SYSTEM__CRITERIA_TOO_MANY_NESTED_CRITERIA';
    final public const INVALID_TYPE = 'FRAMEWORK__INVALID_TYPE';
    final public const CURRENCY_INVALID_EXCEPTION = 'SYSTEM__CURRENCY_INVALID_EXCEPTION';
    final public const COUNTRY_INVALID_EXCEPTION = 'SYSTEM__COUNTRY_INVALID_EXCEPTION';
    final public const COUNTRY_STATE_INVALID_EXCEPTION = 'SYSTEM__COUNTRY_STATE_INVALID_EXCEPTION';
    final public const CHANNEL_CONTEXT_PERMISSIONS_LOCKED = 'SYSTEM__CHANNEL_CONTEXT_PERMISSIONS_LOCKED';
    final public const ENCODING_INVALID_STRUCT_EXCEPTION = 'SYSTEM__ENCODING_INVALID_STRUCT_EXCEPTION';
    final public const ENCODING_MISSING_AGGREGATION_EXCEPTION = 'SYSTEM__ENCODING_MISSING_AGGREGATION_EXCEPTION';
    final public const ORDER_NOT_FOUND_CODE = 'SYSTEM__ORDER_NOT_FOUND_CODE';
    final public const MISSING_ORDER_ASSOCIATION_CODE = 'SYSTEM__MISSING_ORDER_ASSOCIATION_CODE';
    final public const CONTEXT_TOKEN_NOT_ACCESSIBLE = 'SYSTEM__CONTEXT_TOKEN_NOT_ACCESSIBLE';
    final public const CHANNEL_MAPPING_INVALID_OPERATION = 'SYSTEM__CHANNEL_MAPPING_INVALID_OPERATION';
    final public const CHANNEL_FILE_INVALID_PATH = 'FRAMEWORK__CHANNEL_FILE_INVALID_PATH';
    final public const CHANNEL_FILE_INVALID_FILE_FAMILY = 'FRAMEWORK__CHANNEL_FILE_INVALID_FILE_FAMILY';
    final public const CHANNEL_FILE_MISSING_FILE_NAME = 'FRAMEWORK__CHANNEL_FILE_MISSING_FILE_NAME';
    final public const CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES = 'FRAMEWORK__CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES';
    final public const CHANNEL_FILE_NOT_FOUND = 'FRAMEWORK__CHANNEL_FILE_NOT_FOUND';
    final public const CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY = 'FRAMEWORK__CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY';
    private const INVALID_UUID_MESSAGE_TEMPLATE = 'Provided %s is not a valid UUID';

    public static function tooManyNestedCriteria(int $limit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CRITERIA_TOO_MANY_NESTED_CRITERIA,
            'The criteria contains more than {{ limit }} nested criteria.',
            ['limit' => $limit]
        );
    }

    public static function channelNotFound(string $channelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CHANNEL_DOES_NOT_EXISTS_EXCEPTION,
            'Sales channel with id "{{ channelId }}" not found or not valid!.',
            ['channelId' => $channelId]
        );
    }

    public static function currencyNotFound(string $currencyId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'currency', 'field' => 'id', 'value' => $currencyId]
        );
    }

    public static function countryStateNotFound(string $countryStateId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'country state', 'field' => 'id', 'value' => $countryStateId]
        );
    }

    public static function userNotFoundByIdException(string $userId): ContenaHttpException
    {
        return new UserNotFoundByIdException($userId);
    }

    public static function countryNotFound(string $countryId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'id', 'value' => $countryId]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function orderNotFound(string $orderId): self|OrderException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return OrderException::orderNotFound($orderId);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND_CODE,
            self::$couldNotFindMessage,
            ['entity' => 'order', 'field' => 'id', 'value' => $orderId]
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

    public static function invalidLanguageId(): ContenaHttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'language ID'),
        );
    }

    public static function languageNotFound(string $languageId): ContenaHttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId]
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

    public static function unknownPaymentMethod(string $paymentMethodId): ContenaHttpException
    {
        return PaymentException::unknownPaymentMethodById($paymentMethodId);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, as the exception is no longer needed, use RestrictDeleteViolationException instead
     */
    public static function channelDomainInUse(?\Throwable $previous = null): ContenaHttpException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __METHOD__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_DOMAIN_IN_USE,
            'The sales channel domain cannot be deleted because it is still referenced in blog exports.',
            [],
            $previous
        );
    }

    public static function invalidType(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_TYPE,
            $message
        );
    }

    public static function invalidCurrencyId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::CURRENCY_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'currency ID'),
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

    public static function invalidCountryStateId(): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::COUNTRY_STATE_INVALID_EXCEPTION,
            \sprintf(self::INVALID_UUID_MESSAGE_TEMPLATE, 'country state ID'),
        );
    }

    public static function userNotLoggedIn(): CartException
    {
        return CartException::userNotLoggedIn();
    }

    public static function contextPermissionsLocked(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_CONTEXT_PERMISSIONS_LOCKED,
            'Context permission in Channel context already locked.'
        );
    }

    public static function taxNotFound(string $taxId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::TAX_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'tax', 'field' => 'id', 'value' => $taxId]
        );
    }

    public static function userGroupNotFound(string $userGroupId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_GROUP_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'user group', 'field' => 'id', 'value' => $userGroupId]
        );
    }

    public static function shippingMethodNotFound(string $shippingMethodId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SHIPPING_METHOD_DOES_NOT_EXISTS_EXCEPTION,
            self::$couldNotFindMessage,
            ['entity' => 'shipping method', 'field' => 'id', 'value' => $shippingMethodId]
        );
    }

    public static function encodingInvalidStructException(string $context): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_INVALID_STRUCT_EXCEPTION,
            'Invalid struct: "{{ context }}"',
            ['context' => $context]
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

    public static function encodingMissingAggregationException(int|string $key, int $index): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENCODING_MISSING_AGGREGATION_EXCEPTION,
            'Can not find encoded aggregation "{{ key }}" for data index "{{ index }}"',
            ['key' => $key, 'index' => $index]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function missingAssociation(string $association): self|OrderException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return OrderException::missingAssociation($association);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_ORDER_ASSOCIATION_CODE,
            'The required association "{{ association }}" is missing .',
            ['association' => $association]
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
            'The sales channel file path "{{ path }}" is invalid.',
            ['path' => $path]
        );
    }

    public static function invalidChannelFileFamily(string $fileFamily): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_FILE_INVALID_FILE_FAMILY,
            'The sales channel file family "{{ fileFamily }}" is invalid.',
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
            'Could not find sales channel file "{{ fileFamily }}/{{ fileName }}".',
            ['fileFamily' => $fileFamily, 'fileName' => $fileName]
        );
    }

    public static function unexpectedCombinedPrimaryKey(string $entityName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CHANNEL_UNEXPECTED_COMBINED_PRIMARY_KEY,
            'Expected a single field primary key for entity "{{ entityName }}", but got a combined primary key.',
            ['entityName' => $entityName]
        );
    }

    /**
     * The Store API MCP server reports a missing tool result like the Admin API server does.
     */
    public static function mcpToolResultNotFound(string $id): ResourceNotFoundException
    {
        return McpException::toolResultNotFound($id);
    }
}
