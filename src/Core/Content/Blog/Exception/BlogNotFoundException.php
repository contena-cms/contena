<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Content\Blog\BlogException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogNotFoundException extends BlogException
{
    public function __construct(string $blogId)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, self::BLOG_NOT_FOUND, self::$couldNotFindMessage, ['entity' => 'blog', 'field' => 'id', 'value' => $blogId]);
    }
}
