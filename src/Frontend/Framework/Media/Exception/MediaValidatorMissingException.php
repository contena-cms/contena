<?php
declare(strict_types=1);

namespace Contena\Storefront\Framework\Media\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Storefront\Framework\StorefrontFrameworkException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see StorefrontFrameworkException::mediaValidatorMissing} instead
 */
#[Package('discovery')]
class MediaValidatorMissingException extends ContenaHttpException
{
    public function __construct(string $type)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('No validator for {{ type }} was found.', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'STOREFRONT__MEDIA_VALIDATOR_MISSING';
    }
}
