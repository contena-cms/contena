<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class FileContentValidationStrategy
{
    /**
     * @internal
     *
     * @param iterable<AbstractFileContentValidator> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    public function validate(MediaFile $mediaFile): void
    {
        foreach ($this->validators as $validator) {
            if ($validator->supports($mediaFile) === false) {
                continue;
            }

            $validator->validate($mediaFile);
        }
    }
}
