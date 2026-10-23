<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

interface FileUrlValidatorInterface
{
    public function isValid(string $source): bool;
}
