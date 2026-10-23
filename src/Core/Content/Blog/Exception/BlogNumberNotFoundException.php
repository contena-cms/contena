<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogNumberNotFoundException extends ContenaHttpException
{
    public function __construct(string $number)
    {
        parent::__construct(
            'Blog with number "{{ number }}" not found.',
            ['number' => $number]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
