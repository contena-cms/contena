<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Authentication;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
abstract class AbstractStoreRequestOptionsProvider
{
    /**
     * @return array<string, string>
     */
    abstract public function getAuthenticationHeader(Context $context): array;

    /**
     * @return array<string, string>
     */
    abstract public function getDefaultQueryParameters(Context $context): array;

    protected function ensureAdminApiSource(Context $context): AdminApiSource
    {
        $contextSource = $context->getSource();
        if (!$contextSource instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $contextSource::class);
        }

        return $contextSource;
    }
}
