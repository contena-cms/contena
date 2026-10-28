<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class NoConfiguratorFoundException extends ContenaHttpException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            'Product with id {{ productId }} has no configuration.',
            ['productId' => $productId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_HAS_NO_CONFIGURATOR';
    }
}
