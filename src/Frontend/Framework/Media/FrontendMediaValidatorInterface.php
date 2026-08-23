<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FrontendMediaValidatorInterface
{
    /**
     * Returns the supported file type
     */
    public function getType(): string;

    /**
     * Validates the provided file
     */
    public function validate(UploadedFile $file): void;
}
