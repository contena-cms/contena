<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppSeoUrlRouteEntity>
 */
#[Package('framework')]
class AppSeoUrlRouteCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppSeoUrlRouteEntity::class;
    }
}
