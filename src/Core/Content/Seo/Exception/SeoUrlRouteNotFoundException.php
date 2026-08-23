<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Exception;

use Contena\Core\Content\Seo\SeoException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class SeoUrlRouteNotFoundException extends SeoException
{
    public function __construct(string $routeName)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::SEO_URL_ROUTE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'SEO URL route', 'field' => 'name', 'value' => $routeName]
        );
    }
}
