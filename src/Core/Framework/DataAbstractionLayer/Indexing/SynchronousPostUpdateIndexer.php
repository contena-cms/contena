<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Indexing;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class SynchronousPostUpdateIndexer extends PostUpdateIndexer
{
}
