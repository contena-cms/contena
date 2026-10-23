<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api;

use Contena\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorNotFoundException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Api\Exception\InvalidChannelIdException;
use Contena\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Contena\Core\Framework\Api\Exception\InvalidVersionNameException;
use Contena\Core\Framework\Api\Exception\LiveVersionDeleteException;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Api\Exception\NoEntityClonedException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\MissingReverseAssociation;
use Contena\Core\Framework\Routing\Exception\ChannelNotFoundException;

class ApiException extends HttpException
{
    public const API_INVALID_SYNC_CRITERIA_EXCEPTION = 'API_INVALID_SYNC_CRITERIA_EXCEPTION';
    public const API_RESOLVER_NOT_FOUND_EXCEPTION = 'API_RESOLVER_NOT_FOUND_EXCEPTION';
    public const API_UNSUPPORTED_ASSOCIATION_FIELD = 'FRAMEWORK__API_UNSUPPORTED_ASSOCIATION_FIELD_EXCEPTION';
    public const API_INVALID_SYNC_OPERATION_EXCEPTION = 'FRAMEWORK__INVALID_SYNC_OPERATION';
    public const API_INVALID_SCHEMA_DEFINITION_EXCEPTION = 'FRAMEWORK__INVALID_SCHEMA_DEFINITION';
    public const API_SCHEMA_DEFINITION_NOT_READABLE = 'FRAMEWORK__SCHEMA_DEFINITION_NOT_READABLE';
    public const API_NOT_EXISTING_RELATION_EXCEPTION = 'FRAMEWORK__NOT_EXISTING_RELATION_EXCEPTION';
    public const API_UNSUPPORTED_OPERATION_EXCEPTION = 'FRAMEWORK__UNSUPPORTED_OPERATION_EXCEPTION';
    public const API_UNSUPPORTED_STORE_API_SCHEMA_ENDPOINT = 'FRAMEWORK__UNSUPPORTED_STORE_API_SCHEMA_ENDPOINT';
    public const API_UNSUPPORTED_STORE_API_SCHEMA_MIGRATION_SCOPE = 'FRAMEWORK__API_UNSUPPORTED_STORE_API_SCHEMA_MIGRATION_SCOPE';
    public const API_INVALID_VERSION_ID = 'FRAMEWORK__INVALID_VERSION_ID';
    public const API_TYPE_PARAMETER_INVALID = 'FRAMEWORK__API_TYPE_PARAMETER_INVALID';
    public const API_APP_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__APP_ID_PARAMETER_IS_MISSING';
    public const API_CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__API_CHANNEL_ID_PARAMETER_IS_MISSING';
    public const API_CUSTOMER_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__API_CUSTOMER_ID_PARAMETER_IS_MISSING';
    public const API_SHIPPING_COSTS_PARAMETER_IS_MISSING = 'FRAMEWORK__API_SHIPPING_COSTS_PARAMETER_IS_MISSING';
    public const API_UNABLE_GENERATE_BUNDLE = 'FRAMEWORK__API_UNABLE_GENERATE_BUNDLE';
    public const API_INVALID_ACCESS_KEY_EXCEPTION = 'FRAMEWORK__API_INVALID_ACCESS_KEY';
    public const API_OAUTH_INVALID_REDIRECT_URI = 'FRAMEWORK__OAUTH_INVALID_REDIRECT_URI';
    public const API_INVALID_ACCESS_KEY_IDENTIFIER_EXCEPTION = 'FRAMEWORK__API_INVALID_ACCESS_KEY_IDENTIFIER';
    public const API_INVALID_SYNC_RESOLVERS = 'FRAMEWORK__API_INVALID_SYNC_RESOLVERS';
    public const API_CHANNEL_MAINTENANCE_MODE = 'FRAMEWORK__API_CHANNEL_MAINTENANCE_MODE';
    public const API_SYNC_RESOLVER_FIELD_NOT_FOUND = 'FRAMEWORK__API_SYNC_RESOLVER_FIELD_NOT_FOUND';
    public const API_INVALID_ASSOCIATION_FIELD = 'FRAMEWORK__API_INVALID_ASSOCIATION';
    public const API_UNSUPPORTED_ENCODER_INPUT = 'FRAMEWORK__API_UNSUPPORTED_ENCODER_INPUT';
    public const API_INVALID_CONTEXT_SOURCE = 'FRAMEWORK__INVALID_CONTEXT_SOURCE';
    public const API_EXPECTED_USER = 'FRAMEWORK__API_EXPECTED_USER';
    public const API_INVALID_SCOPE_ACCESS_TOKEN = 'FRAMEWORK__INVALID_SCOPE_ACCESS_TOKEN';
    public const API_ROUTES_ARE_LOADED_ALREADY = 'FRAMEWORK__API_ROUTES_ARE_LOADED_ALREADY';
    public const API_NOTIFICATION_THROTTLED = 'FRAMEWORK__NOTIFICATION_THROTTLED';
    public const API_DIRECTORY_NOT_CREATED = 'FRAMEWORK__API_DIRECTORY_NOT_CREATED';
    public const API_MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__API_REQUEST_PARAMETER_MISSING';
    public const API_INVALID_IDS_PARAMETER = 'FRAMEWORK__API_INVALID_IDS_PARAMETER';
    public const INVALID_SCHEMA_FOR_DEFINITION = 'FRAMEWORK__API_INVALID_SCHEMA_FOR_DEFINITION';
    public const API_DEFINITION_GENERATOR_NOT_FOUND = 'FRAMEWORK__API_DEFINITION_GENERATOR_NOT_FOUND';
    public const API_EXPECTATION_NOT_SUPPORTED = 'FRAMEWORK__API_EXPECTATION_NOT_SUPPORTED';

    /**
     * @param list<array{pointer: string, entity: string}> $exceptions
     */
    public static function canNotResolveForeignKeysException(array $exceptions): self
    {
        $message = [];
        $parameters = [];

        foreach ($exceptions as $i => $exception) {
            $message[] = \sprintf(
                'Can not resolve foreign key at position %s. Reference field: %s',
                $exception['pointer'],
                $exception['entity']
            );
            $parameters['pointer-' . $i] = $exception['pointer'];
            $parameters['field-' . $i] = $exception['entity'];
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_RESOLVERS,
            implode("\n", $message),
            $parameters
        );
    }

    public static function invalidSyncCriteriaException(string $operationKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_CRITERIA_EXCEPTION,
            \sprintf('Sync operation %s, with action "delete", requires a criteria with at least one filter and can only be applied for mapping entities', $operationKey)
        );
    }

    public static function invalidSyncOperationException(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_OPERATION_EXCEPTION,
            $message
        );
    }

    public static function resolverNotFoundException(string $key): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_RESOLVER_NOT_FOUND_EXCEPTION,
            \sprintf('Foreign key resolver for key %s not found', $key)
        );
    }

    public static function unsupportedAssociation(string $field): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_ASSOCIATION_FIELD,
            'Unsupported association for field {{ field }}',
            ['field' => $field]
        );
    }

    /**
     * @param list<string> $permissions
     */
    public static function missingPrivileges(array $permissions): ContenaHttpException
    {
        return new MissingPrivilegeException($permissions);
    }

    public static function missingReverseAssociation(string $entity, string $parentEntity): ContenaHttpException
    {
        return new MissingReverseAssociation($entity, $parentEntity);
    }

    public static function definitionNotFound(DefinitionNotFoundException $exception): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            $exception->getErrorCode(),
            $exception->getMessage(),
        );
    }

    public static function pathIsNoAssociationField(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_ASSOCIATION_FIELD,
            'Field "{{ path }}" is not a valid association field.',
            ['path' => $path]
        );
    }

    public static function notExistingRelation(string $path): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::API_NOT_EXISTING_RELATION_EXCEPTION,
            'Resource at path "{{ path }}" is not an existing relation.',
            ['path' => $path]
        );
    }





    public static function noEntityCloned(string $entity, string $id): ContenaHttpException
    {
        return new NoEntityClonedException($entity, $id);
    }

    public static function expectationNotSupported(): self
    {
        return new self(
            Response::HTTP_EXPECTATION_FAILED,
            self::API_EXPECTATION_NOT_SUPPORTED,
            \sprintf(
                'The "%s" header is not supported on endpoints that do not require authentication. Send it with an authenticated Admin API request.',
                PlatformRequest::HEADER_EXPECT_PACKAGES
            )
        );
    }

    /**
     * @param list<string> $fails
     */
    public static function expectationFailed(array $fails): ContenaHttpException
    {
        return new ExpectationFailedException($fails);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public static function invalidSyncOperation(string $message): ContenaHttpException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'),
        );

        return new InvalidSyncOperationException($message);
    }

    public static function invalidChannelId(string $channelId): ContenaHttpException
    {
        return new InvalidChannelIdException($channelId);
    }

    public static function invalidVersionName(): ContenaHttpException
    {
        return new InvalidVersionNameException();
    }

    public static function channelNotFound(): ContenaHttpException
    {
        return new ChannelNotFoundException();
    }

    public static function deleteLiveVersion(): ContenaHttpException
    {
        return new LiveVersionDeleteException();
    }

    /**
     * @param array<mixed> $payload
     */
    public static function resourceNotFound(string $entity, array $payload): ContenaHttpException
    {
        return new ResourceNotFoundException($entity, $payload);
    }

    public static function unsupportedOperation(string $operation): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_OPERATION_EXCEPTION,
            'Unsupported {{ operation }} operation.',
            ['operation' => $operation]
        );
    }

    public static function noPrimaryKeyDefined(string $entityName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_INVALID_SCHEMA_DEFINITION_EXCEPTION,
            'No primary key defined for {{ entityName }}',
            ['entityName' => $entityName]
        );
    }

    public static function mappingFieldNotFound(string $storageField): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_INVALID_SCHEMA_DEFINITION_EXCEPTION,
            'Can not find mapping entity field for storage field {{ storageField }}',
            ['storageField' => $storageField]
        );
    }

    public static function unsupportedStoreApiSchemaEndpoint(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_STORE_API_SCHEMA_ENDPOINT,
            'The Store-API does not support the entity schema endpoint. Use `/store-api/_info/openapi3.json` for the OpenAPI specification.'
        );
    }

    public static function invalidVersionId(string $versionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_VERSION_ID,
            'versionId {{ versionId }} is not a valid uuid.',
            ['versionId' => $versionId]
        );
    }

    public static function invalidApiType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_TYPE_PARAMETER_INVALID,
            'Parameter type {{ type }} is invalid.',
            ['type' => $type]
        );
    }

    public static function appIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_APP_ID_PARAMETER_IS_MISSING,
            'Parameter "id" is missing.',
        );
    }

    public static function channelIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_CHANNEL_ID_PARAMETER_IS_MISSING,
            'Parameter "channelId" is missing.',
        );
    }

    public static function userIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_CUSTOMER_ID_PARAMETER_IS_MISSING,
            'Parameter "userId" is missing.',
        );
    }

    public static function shippingCostsParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_SHIPPING_COSTS_PARAMETER_IS_MISSING,
            'Parameter "shippingCosts" is missing.',
        );
    }

    public static function unableGenerateBundle(string $bundleName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_UNABLE_GENERATE_BUNDLE,
            'Unable to generate bundle directory for bundle "{{ bundleName }}".',
            ['bundleName' => $bundleName]
        );
    }

    public static function schemaDefinitionNotReadable(string $filename): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_SCHEMA_DEFINITION_NOT_READABLE,
            \sprintf('Can\'t read schema file "%s"', $filename),
        );
    }

    public static function invalidSchemaDefinitions(string $filename, \JsonException $exception): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_INVALID_SCHEMA_DEFINITION_EXCEPTION,
            \sprintf('Failed to parse JSON file "%s": %s', $filename, $exception->getMessage()),
        );
    }

    /**
     * @param list<string> $supportedScopes
     */
    public static function unsupportedStoreApiSchemaMigrationScope(string $scope, array $supportedScopes): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_UNSUPPORTED_STORE_API_SCHEMA_MIGRATION_SCOPE,
            'Unsupported Store API schema migration scope "{{ scope }}". Supported scopes are: {{ supportedScopes }}.',
            ['scope' => $scope, 'supportedScopes' => implode(', ', $supportedScopes)],
        );
    }

    public static function invalidSchemaForDefinition(EntityDefinition $definition, string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_SCHEMA_FOR_DEFINITION,
            'Invalid schema for entity "{{ entityName }}". ' . $message,
            ['entityName' => $definition->getEntityName()]
        );
    }

    public static function invalidAccessKey(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::API_INVALID_ACCESS_KEY_EXCEPTION,
            'Access key is invalid and could not be identified.',
        );
    }

    public static function invalidAccessKeyIdentifier(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_INVALID_ACCESS_KEY_IDENTIFIER_EXCEPTION,
            'Given identifier for access key is invalid.',
        );
    }

    public static function invalidOAuthRedirectUri(\Throwable $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_OAUTH_INVALID_REDIRECT_URI,
            'Redirect URL is not registered for this application.',
            previous: $previous,
        );
    }

    public static function channelInMaintenanceMode(): self
    {
        return new self(
            Response::HTTP_SERVICE_UNAVAILABLE,
            self::API_CHANNEL_MAINTENANCE_MODE,
            'The sales channel is in maintenance mode.',
        );
    }

    public static function canNotResolveResolverField(string $entity, string $fieldName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_SYNC_RESOLVER_FIELD_NOT_FOUND,
            'Can not resolve entity field name {{ entity }}.{{ field }} for sync operation resolver',
            ['entity' => $entity, 'field' => $fieldName]
        );
    }

    public static function unsupportedEncoderInput(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_UNSUPPORTED_ENCODER_INPUT,
            'Unsupported encoder data provided. Only entities and entity collections are supported',
        );
    }

    public static function apiRoutesAreAlreadyLoaded(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_ROUTES_ARE_LOADED_ALREADY,
            'API routes are already loaded',
        );
    }

    public static function invalidAdminSource(string $actual): self
    {
        return new InvalidContextSourceException(AdminApiSource::class, $actual);
    }

    public static function admin_userNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::API_EXPECTED_USER,
            'For this interaction an authenticated admin_user login is required.'
        );
    }

    public static function invalidScopeAccessToken(string $identifier): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::API_INVALID_SCOPE_ACCESS_TOKEN,
            'This access token does not have the scope "{{ scope }}" to process this Request',
            ['scope' => $identifier]
        );
    }

    public static function notificationThrottled(int $waitTime, \Throwable $e): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::API_NOTIFICATION_THROTTLED,
            'Notification throttled for {{ seconds }} seconds.',
            ['seconds' => $waitTime],
            $e
        );
    }

    public static function directoryWasNotCreated(string $directory): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::API_DIRECTORY_NOT_CREATED,
            'Directory "{{ directory }}" was not created.',
            ['directory' => $directory]
        );
    }

    public static function missingRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name]
        );
    }

    public static function invalidIdsParameter(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_IDS_PARAMETER,
            'Parameter `ids` is no array or empty',
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function apiDefinitionGeneratorNotFound(string $format): self|ApiDefinitionGeneratorNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new ApiDefinitionGeneratorNotFoundException($format);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_DEFINITION_GENERATOR_NOT_FOUND,
            'Definition generator for format "{{ format }}" not found.',
            ['format' => $format]
        );
    }
}
