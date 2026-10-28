<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamIndexingMessage extends EntityIndexingMessage
{
}
