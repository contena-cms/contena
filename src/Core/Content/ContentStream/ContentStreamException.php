<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream;

use Contena\Core\Content\ContentStream\Exception\EmptyContentStreamException;
use Contena\Core\Content\ContentStream\Exception\NoFilterException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\HttpException;

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
