<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\MediaType;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class AudioType extends MediaType
{
    protected string $name = 'AUDIO';
}
