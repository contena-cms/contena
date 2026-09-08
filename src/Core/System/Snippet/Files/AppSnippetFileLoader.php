<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class AppSnippetFileLoader
{
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @return list<GenericSnippetFile>
     */
    public function loadSnippetFilesFromApp(string $author, string $appPath, bool $isAbsolutePath = false): array
    {
        $snippetDir = ($isAbsolutePath ? $appPath : $this->projectDir . '/' . $appPath) . '/Resources/snippet';
        if (!is_dir($snippetDir)) {
            return [];
        }

        $finder = new Finder()->in($snippetDir)->files()->name('*.json');
        $files = [];
        foreach ($finder->getIterator() as $fileInfo) {
            $parts = explode('.', $fileInfo->getFilenameWithoutExtension());
            $file = match (\count($parts)) {
                2 => new GenericSnippetFile(implode('.', $parts), $fileInfo->getPathname(), $parts[1], $author, false, ''),
                3 => new GenericSnippetFile(implode('.', [$parts[0], $parts[1]]), $fileInfo->getPathname(), $parts[1], $author, $parts[2] === 'base', ''),
                default => null,
            };
            if ($file instanceof GenericSnippetFile) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
