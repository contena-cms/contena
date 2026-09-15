<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\Exception\BlogSortingNotFoundException;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class BlogException extends HttpException
{
    final public const string BLOG_NOT_FOUND = 'CONTENT__BLOG_NOT_FOUND';
    final public const string CATEGORY_NOT_FOUND = 'CONTENT__BLOG_CATEGORY_NOT_FOUND';
    final public const string LISTING_PAGE_OUT_OF_RANGE = 'CONTENT__BLOG_LISTING_PAGE_OUT_OF_RANGE';
    final public const string MISSING_REQUEST_PARAMETER = 'CONTENT__BLOG_MISSING_REQUEST_PARAMETER';
    final public const string INVALID_FIELD_VALUE_TYPE = 'CONTENT__BLOG_INVALID_FIELD_VALUE_TYPE';
    final public const string COMMENTS_NOT_ACTIVE = 'CONTENT__BLOG_COMMENTS_NOT_ACTIVE';
    final public const string COMMENT_PARENT_INVALID = 'CONTENT__BLOG_COMMENT_PARENT_INVALID';

    public static function blogNotFound(string $blogId): BlogNotFoundException
    {
        return new BlogNotFoundException($blogId);
    }

    public static function sortingNotFound(string $key): BlogSortingNotFoundException
    {
        return new BlogSortingNotFoundException($key);
    }

    public static function categoryNotFound(string $categoryId): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::CATEGORY_NOT_FOUND, self::$couldNotFindMessage, ['entity' => 'category', 'field' => 'id', 'value' => $categoryId]);
    }

    public static function pageOutOfRange(int $requestedPage, int $lastPage): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::LISTING_PAGE_OUT_OF_RANGE, 'Requested listing page {{ requestedPage }} is out of range (last page: {{ lastPage }}).', ['requestedPage' => $requestedPage, 'lastPage' => $lastPage]);
    }

    public static function missingRequestParameter(string $name): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::MISSING_REQUEST_PARAMETER, 'Parameter "{{ parameterName }}" is missing.', ['parameterName' => $name]);
    }

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::INVALID_FIELD_VALUE_TYPE, 'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}', ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]);
    }

    public static function commentsNotActive(): self
    {
        return new self(Response::HTTP_FORBIDDEN, self::COMMENTS_NOT_ACTIVE, 'Blog comments are not activated.');
    }

    public static function commentParentInvalid(string $parentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_PARENT_INVALID,
            'Comment "{{ parentId }}" is not a root comment of this blog and channel.',
            ['parentId' => $parentId],
        );
    }
}
