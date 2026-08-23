<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File;

use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;

/**
 * @internal
 */
final class ChannelFileRequestPathResolver
{
    private const MAX_FILE_FAMILY_LENGTH = 64;

    public function buildTemplatePath(string $fileFamily, string $fileName): string
    {
        $this->validateFileFamily($fileFamily);
        $this->validateFileName($fileName);

        return ChannelFile::TEMPLATE_ROOT . '/' . $fileFamily . '/' . $fileName . ChannelFile::TEMPLATE_SUFFIX;
    }

    public function validateFileFamily(string $fileFamily): void
    {
        if ($fileFamily === ''
            || $fileFamily === '.'
            || $fileFamily === '..'
            || mb_strlen($fileFamily) > self::MAX_FILE_FAMILY_LENGTH
            || preg_match('/^[A-Za-z0-9_-]+$/', $fileFamily) !== 1
        ) {
            throw ChannelException::invalidChannelFileFamily($fileFamily);
        }
    }

    private function validateFileName(string $path): void
    {
        if ($path === ''
            || str_starts_with($path, '/')
            || str_ends_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z]:/', $path) === 1
        ) {
            throw ChannelException::invalidChannelFilePath($path);
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9._-]+$/', $segment) !== 1) {
                throw ChannelException::invalidChannelFilePath($path);
            }
        }

        $fileName = (string) end($segments);
        if (pathinfo($fileName, \PATHINFO_EXTENSION) === '' || pathinfo($fileName, \PATHINFO_FILENAME) === '') {
            throw ChannelException::invalidChannelFilePath($path);
        }
    }
}
