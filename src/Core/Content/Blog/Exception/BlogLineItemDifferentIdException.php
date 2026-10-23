<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BlogLineItemDifferentIdException extends ContenaHttpException
{
    public function __construct(string $lineItemId)
    {
        $message = \sprintf('The `blogId` and `referencedId` of the line item %s are not identical.', $lineItemId);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_REFERENCED_ID_DIFFERENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
