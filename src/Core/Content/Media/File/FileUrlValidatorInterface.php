<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

interface FileUrlValidatorInterface
{
    public function isValid(string $source): bool;
}
