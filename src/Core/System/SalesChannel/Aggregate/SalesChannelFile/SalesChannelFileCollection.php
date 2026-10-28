<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelFileEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelFileEntity::class;
    }
}
