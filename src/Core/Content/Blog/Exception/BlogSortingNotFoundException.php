<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogSortingNotFoundException extends ContenaHttpException
{
    public function __construct(string $key)
    {
        parent::__construct(
            'Blog sorting with key {{ key }} not found.',
            ['key' => $key]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_SORTING_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
