<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class NoFilterException extends ContenaHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Blog stream with ID {{ id }} has no filters', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__BLOG_STREAM_MISSING_FILTER';
    }
}
