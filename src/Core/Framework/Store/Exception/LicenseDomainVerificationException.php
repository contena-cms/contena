<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
 */
class LicenseDomainVerificationException extends ContenaHttpException
{
    public function __construct(
        string $domain,
        string $reason = ''
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        $reason = $reason ? (' ' . $reason) : '';
        $message = 'License host verification failed for domain "{{ domain }}.{{ reason }}"';
        parent::__construct($message, ['domain' => $domain, 'reason' => $reason]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_LICENSE_DOMAIN_VALIDATION_FAILED';
    }
}
