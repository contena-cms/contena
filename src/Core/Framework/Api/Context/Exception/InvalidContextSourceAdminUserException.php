<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @deprecated tag:v6.8.0 - Will be removed in v6.8.0.0. Use `\Contena\Core\Framework\Store\StoreException::invalidContextSourceUser` instead.
 */
class InvalidContextSourceUserException extends ContenaHttpException
{
    public function __construct(string $contextSource)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            '{{ contextSource }} does not have a valid admin_user ID',
            ['contextSource' => $contextSource]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE_USER';
    }
}
