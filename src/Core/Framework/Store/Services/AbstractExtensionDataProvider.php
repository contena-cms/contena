<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;

abstract class AbstractExtensionDataProvider
{
    abstract public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection;

    abstract protected function getDecorated(): AbstractExtensionDataProvider;
}
