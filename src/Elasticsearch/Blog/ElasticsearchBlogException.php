<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Blog;

use OpenSearch\Common\Exceptions\BadRequest400Exception;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class ElasticsearchBlogException extends HttpException
{
    public const ES_BLOG_CONFIG_NOT_FOUND = 'ELASTICSEARCH_BLOG__CONFIGURATION_NOT_FOUND';
    public const ES_BLOG_CANNOT_CHANGE_CUSTOM_FIELD_TYPE = 'ELASTICSEARCH_BLOG__CANNOT_CHANGE_CUSTOM_FIELD_TYPE';
    public const ES_BLOG_CANNOT_CHANGE_FIELD_TYPE = 'ELASTICSEARCH_BLOG__CANNOT_CHANGE_FIELD_TYPE';

    public static function configNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ES_BLOG_CONFIG_NOT_FOUND,
            'Configuration for blog elasticsearch definition not found',
        );
    }

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'previous', newType: BadRequestHttpException::class)]
    // @phpstan-ignore parameter.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
    public static function cannotChangeFieldType(BadRequest400Exception|BadRequestHttpException $previous): self
    {
        // @phpstan-ignore instanceof.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
        if ($previous instanceof BadRequest400Exception) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                // @phpstan-ignore classConstant.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
                \sprintf('Passing %s to %s is deprecated and support will be removed in v6.8.0.0. Please pass an instance of %s instead.', BadRequest400Exception::class, __METHOD__, BadRequestHttpException::class)
            );
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_BLOG_CANNOT_CHANGE_FIELD_TYPE,
            'One or more fields already exist in the index with different types. Please reset the index and rebuild it.',
            [],
            $previous,
        );
    }

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'previous', newType: BadRequestHttpException::class)]
    // @phpstan-ignore parameter.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
    public static function cannotChangeCustomFieldType(BadRequest400Exception|BadRequestHttpException $previous): self
    {
        // @phpstan-ignore instanceof.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
        if ($previous instanceof BadRequest400Exception) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                // @phpstan-ignore classConstant.deprecatedClass (BadRequest400Exception is deprecated upstream; this BC path is removed in v6.8.0.)
                \sprintf('Passing %s to %s is deprecated and support will be removed in v6.8.0.0. Please pass an instance of %s instead.', BadRequest400Exception::class, __METHOD__, BadRequestHttpException::class)
            );
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ES_BLOG_CANNOT_CHANGE_CUSTOM_FIELD_TYPE,
            'One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.',
            [],
            $previous,
        );
    }
}
