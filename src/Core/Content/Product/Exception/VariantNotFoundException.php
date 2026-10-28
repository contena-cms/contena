<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Content\Product\ProductException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class VariantNotFoundException extends ProductException
{
    /**
     * @param array<string> $options
     */
    public function __construct(
        string $productId,
        array $options
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::PRODUCT_VARIANT_NOT_FOUND,
            'Variant for productId {{ productId }} with options {{ options }} not found.',
            [
                'productId' => $productId,
                'options' => json_encode($options, \JSON_THROW_ON_ERROR),
            ]
        );
    }
}
