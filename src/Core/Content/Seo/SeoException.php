<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class SeoException extends HttpException
{
    public const CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__CHANNEL_ID_PARAMETER_IS_MISSING';
    public const TEMPLATE_PARAMETER_IS_MISSING = 'FRAMEWORK__TEMPLATE_PARAMETER_IS_MISSING';
    public const ROUTE_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ROUTE_NAME_PARAMETER_IS_MISSING';
    public const ENTITY_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ENTITY_NAME_PARAMETER_IS_MISSING';
    public const CHANNEL_NOT_FOUND = 'FRAMEWORK__CHANNEL_NOT_FOUND';
    public const SEO_URL_ROUTE_NOT_FOUND = 'CONTENT__SEO_URL_ROUTE_NOT_FOUND';
    public const APP_SEO_URL_PATH_INVALID = 'CONTENT__SEO_APP_SEO_URL_PATH_INVALID';
    public const APP_SEO_URL_PATH_ALREADY_REGISTERED = 'CONTENT__SEO_APP_SEO_URL_PATH_ALREADY_REGISTERED';
    public const APP_SEO_URL_PATH_IN_USE = 'CONTENT__SEO_APP_SEO_URL_PATH_IN_USE';
    public const APP_SEO_URL_HOOK_ALREADY_REGISTERED = 'CONTENT__SEO_APP_SEO_URL_HOOK_ALREADY_REGISTERED';
    public const APP_ENTITY_SEO_URL_ENTITY_UNSUPPORTED = 'CONTENT__SEO_APP_ENTITY_SEO_URL_ENTITY_UNSUPPORTED';
    public const APP_ENTITY_SEO_URL_NOT_PERMITTED = 'CONTENT__SEO_APP_ENTITY_SEO_URL_NOT_PERMITTED';
    /**
     * @internal tag:v6.8.0 - Will be removed once $context is required in event constructors
     */
    public const INVALID_EVENT_DATA = 'CONTENT__SEO__INVALID_EVENT_DATA';

    /**
     * @internal tag:v6.8.0 - Will be removed once $context is required in event constructors
     */
    public static function invalidEventData(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_EVENT_DATA,
            $message
        );
    }

    public static function invalidChannelId(string $channelId): ContenaHttpException
    {
        return new InvalidChannelIdException($channelId);
    }

    public static function channelIdParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_ID_PARAMETER_IS_MISSING,
            'Parameter "channelId" is missing.',
        );
    }

    public static function templateParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_PARAMETER_IS_MISSING,
            'Parameter "template" is missing.',
        );
    }

    public static function entityNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ENTITY_NAME_PARAMETER_IS_MISSING,
            'Parameter "entityName" is missing.',
        );
    }

    public static function routeNameParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROUTE_NAME_PARAMETER_IS_MISSING,
            'Parameter "routeName" is missing.',
        );
    }

    public static function channelNotFound(string $channelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CHANNEL_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sales channel', 'field' => 'id', 'value' => $channelId]
        );
    }

    public static function seoUrlRouteNotFound(string $routeName): ContenaHttpException
    {
        return new SeoUrlRouteNotFoundException($routeName);
    }

    public static function noEntitiesForPreview(string $entityName, string $routeName): ContenaHttpException
    {
        return new NoEntitiesForPreviewException($entityName, $routeName);
    }

    public static function invalidTemplate(string $message): ContenaHttpException
    {
        return new InvalidTemplateException($message);
    }

    public static function unexpectedType(mixed $givenType, string $expectedType): UnexpectedTypeException
    {
        return new UnexpectedTypeException($givenType, $expectedType);
    }

    public static function appSeoUrlPathInvalid(string $seoUrlName, string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_SEO_URL_PATH_INVALID,
            'The path "{{ path }}" of the SEO URL "{{ seoUrlName }}" contains characters that are not allowed in URLs.',
            ['path' => $path, 'seoUrlName' => $seoUrlName],
        );
    }

    public static function appSeoUrlPathAlreadyRegistered(string $seoUrlName, string $path, string $owningApp): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_SEO_URL_PATH_ALREADY_REGISTERED,
            'The path "{{ path }}" of the SEO URL "{{ seoUrlName }}" is already registered by app "{{ owningApp }}".',
            ['path' => $path, 'seoUrlName' => $seoUrlName, 'owningApp' => $owningApp],
        );
    }

    public static function appSeoUrlPathInUse(string $seoUrlName, string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_SEO_URL_PATH_IN_USE,
            'The path "{{ path }}" of the SEO URL "{{ seoUrlName }}" is already used by a frontend route or another SEO URL.',
            ['path' => $path, 'seoUrlName' => $seoUrlName],
        );
    }

    public static function appSeoUrlHookAlreadyRegistered(string $seoUrlName, string $hook, string $owningApp): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_SEO_URL_HOOK_ALREADY_REGISTERED,
            'The hook "{{ hook }}" of the SEO URL "{{ seoUrlName }}" is already used by app "{{ owningApp }}".',
            ['hook' => $hook, 'seoUrlName' => $seoUrlName, 'owningApp' => $owningApp],
        );
    }

    public static function appEntitySeoUrlEntityUnsupported(string $seoUrlName, string $entityName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_ENTITY_SEO_URL_ENTITY_UNSUPPORTED,
            'The entity "{{ entityName }}" of the SEO URL "{{ seoUrlName }}" cannot have SEO URLs.',
            ['entityName' => $entityName, 'seoUrlName' => $seoUrlName],
        );
    }

    /**
     * @param list<string> $privileges
     */
    public static function appEntitySeoUrlNotPermitted(string $seoUrlName, string $entityName, array $privileges): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_ENTITY_SEO_URL_NOT_PERMITTED,
            'The SEO URL "{{ seoUrlName }}" needs the permissions {{ privileges }} for the entity "{{ entityName }}".',
            ['seoUrlName' => $seoUrlName, 'privileges' => implode(', ', $privileges), 'entityName' => $entityName],
        );
    }
}
