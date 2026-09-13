<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppSeoUrlRouteEntity>
 */
class AppSeoUrlRouteCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppSeoUrlRouteEntity::class;
    }
}
