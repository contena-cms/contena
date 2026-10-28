<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractProductSearchQueryBuilder
{
    abstract public function getDecorated(): AbstractProductSearchQueryBuilder;

    abstract public function build(Criteria $criteria, Context $context): BuilderInterface;
}
