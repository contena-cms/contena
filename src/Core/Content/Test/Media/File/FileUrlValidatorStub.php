<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Media\File;

use Contena\Core\Content\Media\File\FileUrlValidatorInterface;

/**
 * @internal
 */
class FileUrlValidatorStub implements FileUrlValidatorInterface
{
    public function isValid(string $source): bool
    {
        $host = parse_url($source, \PHP_URL_HOST);

        if ($host === false || $host === null) {
            return false;
        }

        return true;
    }
}
