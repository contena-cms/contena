<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use CartException::invalidPriceFieldTypeException() or DataAbstractionLayerException::invalidPriceFieldType() instead
 */
class InvalidPriceFieldTypeException extends ContenaHttpException
{
    public function __construct(string $type)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'The price field does not contain a valid "type" value. Received {{ type }} ',
            ['type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PRICE_FIELD_TYPE';
    }
}
