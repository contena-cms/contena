<?php declare(strict_types=1);

namespace Contena\Tests\Architecture;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ContenaNamespaceTest extends TestCase
{
    #[TestDox('Tracked paths and files contain no legacy project branding')]
    public function testLegacyBrandDoesNotRemain(): void
    {
        $projectRoot = \dirname(__DIR__, 2);
        $legacyBrand = implode('', ['shop', 'ware']);
        $violations = [];

        foreach ($this->trackedFiles($projectRoot) as $relativePath) {
            $absolutePath = $projectRoot . '/' . $relativePath;

            if (!is_file($absolutePath) && !is_link($absolutePath)) {
                continue;
            }

            if (str_contains(strtolower($relativePath), $legacyBrand)) {
                $violations[] = $relativePath . ' (path)';
            }

            if (is_dir($absolutePath)) {
                continue;
            }

            $contents = file_get_contents($absolutePath);
            static::assertIsString($contents);

            if (str_contains(strtolower($contents), $legacyBrand)) {
                $violations[] = $relativePath . ' (content)';
            }
        }

        static::assertSame([], $violations, 'Legacy project branding remains in: ' . implode(', ', $violations));
    }

    /**
     * @return list<string>
     */
    private function trackedFiles(string $projectRoot): array
    {
        $command = \sprintf('git -C %s ls-files -z', escapeshellarg($projectRoot));
        $output = shell_exec($command);

        if (!\is_string($output)) {
            throw new \RuntimeException('Unable to list tracked project files');
        }

        $files = array_values(array_filter(explode("\0", $output)));
        sort($files);

        return $files;
    }
}
