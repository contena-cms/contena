<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
interface FileUrlValidatorInterface
{
    public function isValid(string $source): bool;
}
