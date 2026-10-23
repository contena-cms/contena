<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
abstract class AbstractExtensionDataProvider
{
    abstract public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection;

    abstract public function getAppEntityFromTechnicalName(string $technicalName, Context $context): AppEntity;

    abstract public function getAppEntityFromId(string $id, Context $context): AppEntity;

    abstract protected function getDecorated(): AbstractExtensionDataProvider;
}
