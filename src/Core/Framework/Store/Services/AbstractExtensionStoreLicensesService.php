<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Struct\ReviewStruct;

/**
 * @internal
 */
abstract class AbstractExtensionStoreLicensesService
{
    abstract public function cancelSubscription(int $licenseId, Context $context): void;

    abstract public function rateLicensedExtension(ReviewStruct $rating, Context $context): void;

    abstract protected function getDecorated(): AbstractExtensionStoreLicensesService;
}
