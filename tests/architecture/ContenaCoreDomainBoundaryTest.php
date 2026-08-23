<?php declare(strict_types=1);

namespace Contena\Tests\Architecture;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ContenaCoreDomainBoundaryTest extends TestCase
{
    private const string BASELINE_FILE = __DIR__ . '/baseline/allowed-core-domain-dependencies.txt';

    private const array FORBIDDEN_NAMESPACE_PREFIXES = [
        'Contena\\Core\\Checkout',
        'Contena\\Core\\Content\\Category',
        'Contena\\Core\\Content\\Cms',
        'Contena\\Core\\Content\\LandingPage',
        'Contena\\Core\\Content\\Newsletter',
        'Contena\\Core\\Content\\Product',
        'Contena\\Core\\Content\\ProductExport',
        'Contena\\Core\\Content\\ProductStream',
        'Contena\\Core\\Content\\Property',
        'Contena\\Core\\Content\\Rule',
        'Contena\\Core\\Content\\Seo',
        'Contena\\Core\\Framework\\App',
        'Contena\\Core\\System\\SalesChannel',
        'Contena\\Core\\System\\Tax',
        'Contena\\Storefront',
    ];

    private const array PROTECTED_PATHS = [
        'src/Administration',
        'src/Core/Framework',
        'src/Core/Kernel.php',
        'src/Core/System/Country',
        'src/Core/System/SystemConfig',
        'src/Core/System/User',
    ];

    #[TestDox('Kernel, DAL, identity and Administration introduce no new Commerce/App/Storefront dependencies')]
    public function testProtectedCoreDoesNotIntroduceNewDomainDependencies(): void
    {
        $baseline = $this->readBaseline();
        $current = $this->scanDependencies();
        $unexpected = array_values(array_diff($current, $baseline));

        static::assertSame(
            [],
            $unexpected,
            "Unexpected protected-core dependencies were added:\n" . implode("\n", $unexpected),
        );
    }

    #[TestDox('The temporary dependency baseline is deterministic and reviewable')]
    public function testBaselineIsSortedAndUnique(): void
    {
        $baseline = $this->readBaseline();
        $sortedBaseline = array_values(array_unique($baseline));
        sort($sortedBaseline);

        static::assertSame($sortedBaseline, $baseline);
    }

    /**
     * @return list<string>
     */
    private function readBaseline(): array
    {
        $baseline = file(self::BASELINE_FILE, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        static::assertIsArray($baseline);

        return $baseline;
    }

    /**
     * @return list<string>
     */
    private function scanDependencies(): array
    {
        $projectRoot = \dirname(__DIR__, 2);
        $violations = [];

        foreach ($this->phpFiles($projectRoot) as $file) {
            $contents = file_get_contents($file);
            static::assertIsString($contents);

            preg_match_all('/^use (Contena\\\\[^;]+);$/m', $contents, $matches);

            foreach ($matches[1] as $dependency) {
                foreach (self::FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
                    if ($dependency !== $prefix && !str_starts_with($dependency, $prefix . '\\')) {
                        continue;
                    }

                    $relativePath = substr($file, \strlen($projectRoot) + 1);
                    $violations[] = $relativePath . "\t" . $dependency;

                    break;
                }
            }
        }

        $violations = array_values(array_unique($violations));
        sort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $projectRoot): array
    {
        $files = [];

        foreach (self::PROTECTED_PATHS as $protectedPath) {
            $absolutePath = $projectRoot . '/' . $protectedPath;

            if (is_file($absolutePath)) {
                $files[] = $absolutePath;

                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolutePath));

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo) {
                    throw new \RuntimeException('Expected a filesystem entry');
                }

                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
