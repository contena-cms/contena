<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Media;

use Contena\Frontend\Framework\FrontendFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FrontendMediaValidatorRegistry
{
    /**
     * @internal
     *
     * @param iterable<FrontendMediaValidatorInterface> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    public function validate(UploadedFile $file, string $type): void
    {
        $filtered = [];
        foreach ($this->validators as $validator) {
            if (mb_strtolower($validator->getType()) === mb_strtolower($type)) {
                $filtered[] = $validator;
            }
        }

        if ($filtered === []) {
            throw FrontendFrameworkException::mediaValidatorMissing($type);
        }

        foreach ($filtered as $validator) {
            $validator->validate($file);
        }
    }
}
