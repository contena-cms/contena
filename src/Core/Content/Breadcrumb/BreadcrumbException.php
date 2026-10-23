<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb;

use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;

class BreadcrumbException extends CategoryException
{
    public const BREADCRUMB_CATEGORY_NOT_FOUND = 'BREADCRUMB_CATEGORY_NOT_FOUND';

    public static function categoryNotFoundForBlog(string $blogId): self
    {
        return new self(
            Response::HTTP_NO_CONTENT,
            self::BREADCRUMB_CATEGORY_NOT_FOUND,
            'The main category for blog {{ blogId }} is not found',
            ['blogId' => $blogId]
        );
    }

    public static function categoryNotFound(string $id): ContenaHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function blogNotFound(string $id): ContenaHttpException
    {
        return new BlogNotFoundException($id);
    }
}
