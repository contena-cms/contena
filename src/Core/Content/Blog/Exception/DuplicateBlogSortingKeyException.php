<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateBlogSortingKeyException extends ContenaHttpException
{
    public function __construct(string $key, \Throwable $exception)
    {
        parent::__construct(
            'Sorting with key "{{ key }}" already exists.',
            ['key' => $key],
            $exception
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_BLOG_SORTING_KEY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
