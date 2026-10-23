<?php declare(strict_types=1);

namespace Contena\Frontend\Controller\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class FrontendException extends HttpException
{
    final public const CAN_NOT_RENDER_VIEW = 'FRONTEND__CAN_NOT_RENDER_VIEW';
    final public const CAN_NOT_RENDER_CUSTOM_APP_VIEW = 'FRONTEND__CAN_NOT_RENDER_CUSTOM_APP_VIEW';
    final public const UN_SUPPORT_FRONTEND_RESPONSE = 'FRONTEND__UN_SUPPORT_FRONTEND_RESPONSE';
    final public const CLASS_DONT_HAVE_TWIG_INJECTED = 'FRONTEND__CLASS_DONT_HAVE_TWIG_INJECTED';
    final public const NO_REQUEST_PROVIDED = 'FRONTEND__NO_REQUEST_PROVIDED';
    /**
     * @deprecated tag:v6.8.0 - Will be replaced by `BlogException::BLOG_REVIEW_NOT_ACTIVE`
     */
    final public const BLOG_REVIEW_NOT_ACTIVE = 'FRONTEND__REVIEW_NOT_ACTIVE';
    final public const CHANNEL_DOMAIN_NOT_FOUND = 'FRONTEND__CHANNEL_DOMAIN_NOT_FOUND';

    private const CUSTOM_APP_PATH = 'custom/apps/';


    public static function unSupportFrontendResponse(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UN_SUPPORT_FRONTEND_RESPONSE,
            'Symfony render implementation changed. Providing a response is no longer supported'
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement as it is unused
     */
    public static function dontHaveTwigInjected(string $class): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CLASS_DONT_HAVE_TWIG_INJECTED,
            'Class {{ class }} does not have twig injected. Add to your service definition a method call to setTwig with the twig instance',
            ['class' => $class]
        );
    }

    public static function noRequestProvided(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_REQUEST_PROVIDED,
            'No request is available.This controller action require an active request context.'
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be replaced by `BlogException::reviewNotActive`
     */
    public static function reviewNotActive(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'BlogException::reviewNotActive')
        );

        return new self(
            Response::HTTP_FORBIDDEN,
            self::BLOG_REVIEW_NOT_ACTIVE,
            'Reviews not activated'
        );
    }


    /**
     * Throwing the custom exception allows to still catch {@see RouteNotFoundException} as usual
     */
    public static function routeNotFound(string $route, ?\Throwable $previous = null): FrontendRouteNotFoundException
    {
        return new FrontendRouteNotFoundException($route, $previous);
    }
}
