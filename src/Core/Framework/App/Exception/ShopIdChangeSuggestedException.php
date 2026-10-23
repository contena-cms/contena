<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Contena\Core\Framework\App\ShopId\ShopId;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ShopIdChangeSuggestedException extends AppException
{
    public function __construct(
        public readonly ShopId $shopId,
        public readonly FingerprintComparisonResult $comparisonResult,
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            AppException::SHOP_ID_CHANGE_SUGGESTED,
            'Changes in your system were detected that suggest a change of the shop ID.'
        );
    }
}
