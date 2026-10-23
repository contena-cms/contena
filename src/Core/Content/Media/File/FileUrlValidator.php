<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

class FileUrlValidator implements FileUrlValidatorInterface
{
    private readonly TrustedUrlResolver $resolver;

    /**
     * @internal
     */
    public function __construct(?TrustedUrlResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new TrustedUrlResolver();
    }

    public function isValid(string $source): bool
    {
        return $this->resolver->isValid($source);
    }
}
