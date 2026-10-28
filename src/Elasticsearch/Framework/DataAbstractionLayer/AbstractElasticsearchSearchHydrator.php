<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Framework\DataAbstractionLayer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractElasticsearchSearchHydrator
{
    abstract public function getDecorated(): AbstractElasticsearchSearchHydrator;

    abstract public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): IdSearchResult;
}
