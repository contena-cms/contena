<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppPaymentMethod;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppPaymentMethodEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppPaymentMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppPaymentMethodEntity::class;
    }
}
