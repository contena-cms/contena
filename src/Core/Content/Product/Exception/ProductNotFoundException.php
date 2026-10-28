<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Content\Product\ProductException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductNotFoundException extends ProductException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            Feature::isActive('v6.8.0.0') ? self::PRODUCT_NOT_FOUND : 'CONTENT__PRODUCT_NOT_FOUND',
            self::$couldNotFindMessage,
            ['entity' => 'product', 'field' => 'id', 'value' => $productId]
        );
    }
}
