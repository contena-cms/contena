<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream;

use Contena\Core\Content\BlogStream\Exception\EmptyBlogStreamException;
use Contena\Core\Content\BlogStream\Exception\NoFilterException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\HttpException;

class BlogStreamException extends HttpException
{
    public static function blogStreamNotFound(string $id): ContenaHttpException
    {
        return new EntityNotFoundException('blog_stream', $id);
    }

    public static function noFilters(string $id): ContenaHttpException
    {
        return new NoFilterException($id);
    }

    public static function emptyBlogStream(string $id): EmptyBlogStreamException
    {
        return new EmptyBlogStreamException($id);
    }
}
