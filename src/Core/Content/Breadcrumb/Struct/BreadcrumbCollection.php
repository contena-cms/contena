<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Breadcrumb>
 */
class BreadcrumbCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'breadcrumb_collection';
    }

    protected function getExpectedClass(): string
    {
        return Breadcrumb::class;
    }
}
