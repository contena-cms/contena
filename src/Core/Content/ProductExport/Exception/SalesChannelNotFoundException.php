<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SalesChannelNotFoundException extends ContenaHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Sales channel with ID {{ id }} not found', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_SALES_CHANNEL_NOT_FOUND';
    }
}
