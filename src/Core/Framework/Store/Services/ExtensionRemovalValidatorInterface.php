<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\StoreException;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
interface ExtensionRemovalValidatorInterface
{
    /**
     * @throws StoreException when the extension is still in use and must not be removed
     */
    public function validateCanBeRemoved(string $technicalName, string $extensionId, Context $context): void;
}
