<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Util;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginException;

class PluginIdProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(private readonly EntityRepository $pluginRepo)
    {
    }

    public function getPluginIdByBaseClass(string $pluginBaseClassName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('baseClass', $pluginBaseClassName));
        $id = $this->pluginRepo->searchIds($criteria, $context)->firstId();
        if ($id === null) {
            throw PluginException::notFound($pluginBaseClassName);
        }

        return $id;
    }
}
