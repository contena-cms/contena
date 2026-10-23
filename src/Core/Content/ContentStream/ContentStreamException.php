<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

class ContentStreamException extends HttpException
{
    public static function contentStreamNotFound(string $id): ContenaHttpException
    {
        return new EntityNotFoundException('content_stream', $id);
    }

    public static function noFilters(string $id): ContenaHttpException
    {
        return new NoFilterException($id);
    }

    public static function emptyBlogStream(string $id): EmptyContentStreamException
    {
        return new EmptyContentStreamException($id);
    }
}
