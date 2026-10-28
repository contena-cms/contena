<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream;

use Contena\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Contena\Core\Content\ProductStream\Exception\NoFilterException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

#[Package('inventory')]
class ProductStreamException extends HttpException
{
    public static function productStreamNotFound(string $id): ContenaHttpException
    {
        return new EntityNotFoundException('product_stream', $id);
    }

    public static function noFilters(string $id): ContenaHttpException
    {
        return new NoFilterException($id);
    }

    public static function emptyProductStream(string $id): EmptyProductStreamException
    {
        return new EmptyProductStreamException($id);
    }
}
