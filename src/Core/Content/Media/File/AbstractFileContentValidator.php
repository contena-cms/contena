<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

abstract class AbstractFileContentValidator
{
    abstract public function getDecorated(): AbstractFileContentValidator;

    abstract public function supports(MediaFile $mediaFile): bool;

    abstract public function validate(MediaFile $mediaFile): void;
}
