<?php
declare(strict_types=1);

namespace Contena\Frontend\Framework\Media\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Frontend\Framework\FrontendFrameworkException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see FrontendFrameworkException::mediaValidatorMissing} instead
 */
class MediaValidatorMissingException extends ContenaHttpException
{
    public function __construct(string $type)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('No validator for {{ type }} was found.', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'FRONTEND__MEDIA_VALIDATOR_MISSING';
    }
}
