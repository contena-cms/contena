<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogLineItemInconsistentException extends ContenaHttpException
{
    public function __construct(string $lineItemId)
    {
        $message = \sprintf(
            'To change the blog of line item (%s), the following properties must also be updated: `blogId`, `referencedId`, `payload.blogNumber`.',
            $lineItemId
        );

        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_LINE_ITEM_INCONSISTENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
