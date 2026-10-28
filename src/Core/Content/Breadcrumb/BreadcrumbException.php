<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb;

use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Product\Exception\ProductNotFoundException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class BreadcrumbException extends CategoryException
{
    public const BREADCRUMB_CATEGORY_NOT_FOUND = 'BREADCRUMB_CATEGORY_NOT_FOUND';

    public static function categoryNotFoundForProduct(string $productId): self
    {
        return new self(
            Response::HTTP_NO_CONTENT,
            self::BREADCRUMB_CATEGORY_NOT_FOUND,
            'The main category for product {{ productId }} is not found',
            ['productId' => $productId]
        );
    }

    public static function categoryNotFound(string $id): ContenaHttpException
    {
        return new CategoryNotFoundException($id);
    }

    public static function productNotFound(string $id): ContenaHttpException
    {
        return new ProductNotFoundException($id);
    }
}
