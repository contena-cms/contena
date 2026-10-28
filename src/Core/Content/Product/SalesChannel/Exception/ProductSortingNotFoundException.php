<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSortingNotFoundException extends ContenaHttpException
{
    public function __construct(string $key)
    {
        parent::__construct(
            'Product sorting with key {{ key }} not found.',
            ['key' => $key]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_SORTING_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
