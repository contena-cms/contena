<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductLineItemDifferentIdException extends ContenaHttpException
{
    public function __construct(string $lineItemId)
    {
        $message = \sprintf('The `productId` and `referencedId` of the line item %s are not identical.', $lineItemId);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_REFERENCED_ID_DIFFERENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
