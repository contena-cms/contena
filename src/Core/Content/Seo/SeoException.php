<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Seo\Exception\InvalidTemplateException;
use Contena\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Contena\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Contena\Core\Framework\Api\Exception\InvalidChannelIdException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SeoException extends HttpException
{
    public const CHANNEL_ID_PARAMETER_IS_MISSING = 'FRAMEWORK__CHANNEL_ID_PARAMETER_IS_MISSING';
    public const TEMPLATE_PARAMETER_IS_MISSING = 'FRAMEWORK__TEMPLATE_PARAMETER_IS_MISSING';
    public const ROUTE_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ROUTE_NAME_PARAMETER_IS_MISSING';
    public const ENTITY_NAME_PARAMETER_IS_MISSING = 'FRAMEWORK__ENTITY_NAME_PARAMETER_IS_MISSING';
    public const CHANNEL_NOT_FOUND = 'FRAMEWORK__CHANNEL_NOT_FOUND';
    public const DATA_SCOPE_MISMATCH = 'CONTENT__SEO_DATA_SCOPE_MISMATCH';
    public const SEO_URL_ROUTE_NOT_FOUND = 'CONTENT__SEO_URL_ROUTE_NOT_FOUND';

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
            ['entity' => 'channel', 'field' => 'id', 'value' => $channelId]
        );
    }

    public static function seoUrlRouteNotFound(string $routeName): HttpException
    {
        return new SeoUrlRouteNotFoundException($routeName);
    }

    public static function noEntitiesForPreview(string $entityName, string $routeName): HttpException
    {
        return new NoEntitiesForPreviewException($entityName, $routeName);
    }

    public static function invalidTemplate(string $message): HttpException
    {
        return new InvalidTemplateException($message);
    }

    public static function dataScopeMismatch(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DATA_SCOPE_MISMATCH,
            'The channel and SEO URL context must belong to the same data scope.',
        );
    }

    public static function unexpectedType(mixed $givenType, string $expectedType): UnexpectedTypeException
    {
        return new UnexpectedTypeException($givenType, $expectedType);
    }
}
