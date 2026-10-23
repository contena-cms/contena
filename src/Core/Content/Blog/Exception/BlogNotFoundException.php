<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogNotFoundException extends BlogException
{
    public function __construct(string $blogId)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            Feature::isActive('v6.8.0.0') ? self::BLOG_NOT_FOUND : 'CONTENT__BLOG_NOT_FOUND',
            self::$couldNotFindMessage,
            ['entity' => 'blog', 'field' => 'id', 'value' => $blogId]
        );
    }
}
