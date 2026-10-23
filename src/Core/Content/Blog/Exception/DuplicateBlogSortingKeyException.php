<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateBlogSortingKeyException extends ContenaHttpException
{
    public function __construct(
        string $key,
        \Throwable $e
    ) {
        parent::__construct(
            'Sorting with key "{{ key }}" already exists.',
            ['key' => $key],
            $e
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
