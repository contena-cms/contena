<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateBlogNumberException extends ContenaHttpException
{
    public function __construct(
        string $number,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Blog with number "{{ number }}" already exists.',
            ['number' => $number],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_BLOG_NUMBER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
