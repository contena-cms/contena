<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\MediaType;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class BinaryType extends MediaType
{
    protected string $name = 'BINARY';
}
