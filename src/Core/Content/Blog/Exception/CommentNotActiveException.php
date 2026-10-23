<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use BlogException::commentNotActive instead
 */
class CommentNotActiveException extends ContenaHttpException
{
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('Reviews not activated');
    }

    public function getErrorCode(): string
    {
        return 'BLOG__REVIEW_NOT_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
