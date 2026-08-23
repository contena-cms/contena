<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Resource\ExtensionsResource;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(ExtensionsResource::class)]
class ExtensionsResourceTest extends TestCase
{
    public function testReturnsCorrectUriAndMimeType(): void
    {
        $result = ($this->makeResource())();

        static::assertSame('contena://extensions', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
    }

    public function testBundleNotRegistered(): void
    {
        $data = $this->invokeWithExtensions(
            [['name' => 'CtMcpExampleBundle', 'type' => 'bundle', 'tool_prefix' => 'example-', 'description' => 'Test bundle', 'install_command' => 'composer require ct/example', 'documentation_url' => null]],
            registeredBundles: [],
        );

        $bundle = $this->findExtension($data, 'CtMcpExampleBundle');
        static::assertSame('not_installed', $bundle['status']);
        static::assertSame('composer require ct/example', $bundle['install_command']);
    }

    public function testBundleRegisteredIsActive(): void
    {
        $bundleMock = static::createStub(BundleInterface::class);
        $data = $this->invokeWithExtensions(
            [['name' => 'CtMcpExampleBundle', 'type' => 'bundle', 'tool_prefix' => 'example-', 'description' => 'Test bundle', 'install_command' => 'composer require ct/example', 'documentation_url' => null]],
            registeredBundles: ['CtMcpExampleBundle' => $bundleMock],
        );

        $bundle = $this->findExtension($data, 'CtMcpExampleBundle');
        static::assertSame('active', $bundle['status']);
        static::assertNull($bundle['install_command']);
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     * @param array<string, string> $pluginRows
     * @param array<string, string> $appRows
     * @param array<string, BundleInterface> $registeredBundles
     *
     * @return list<array<string, mixed>>
     */
    private function invokeWithExtensions(array $extensions, array $pluginRows = [], array $appRows = [], array $registeredBundles = []): array
    {
        $resource = $this->makeResourceWithExtensions($extensions, $pluginRows, $appRows, $registeredBundles);

        return $this->invoke($resource);
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     * @param array<string, string> $pluginRows
     * @param array<string, string> $appRows
     * @param array<string, BundleInterface> $registeredBundles
     */
    private function makeResourceWithExtensions(array $extensions, array $pluginRows = [], array $appRows = [], array $registeredBundles = []): ExtensionsResource
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(
            static function (string $sql) use ($pluginRows, $appRows): array {
                if (str_contains($sql, '`plugin`')) {
                    return $pluginRows;
                }
                if (str_contains($sql, '`app`')) {
                    return $appRows;
                }

                return [];
            }
        );

        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($registeredBundles);

        return new class($connection, $kernel, $extensions) extends ExtensionsResource {
            /**
             * @param list<array{name: string, type: 'plugin'|'bundle', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $testExtensions
             */
            public function __construct(
                Connection $connection,
                KernelInterface $kernel,
                private readonly array $testExtensions,
            ) {
                parent::__construct($connection, $kernel);
            }

            protected function getKnownExtensions(): array
            {
                return $this->testExtensions;
            }
        };
    }

    /**
     * @param array<string, string> $pluginRows plugin name → active flag ('0'|'1')
     * @param array<string, string> $appRows app name → active flag ('0'|'1')
     * @param array<string, BundleInterface> $registeredBundles bundle name → instance
     */
    private function makeResource(array $pluginRows = [], array $appRows = [], array $registeredBundles = []): ExtensionsResource
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(
            static function (string $sql) use ($pluginRows, $appRows): array {
                if (str_contains($sql, '`plugin`')) {
                    return $pluginRows;
                }
                if (str_contains($sql, '`app`')) {
                    return $appRows;
                }

                return [];
            }
        );

        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($registeredBundles);

        return new ExtensionsResource($connection, $kernel);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoke(ExtensionsResource $resource): array
    {
        $result = ($resource)();

        return json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $data
     *
     * @return array<string, mixed>
     */
    private function findExtension(array $data, string $name): array
    {
        foreach ($data as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        static::fail(\sprintf('Extension "%s" not found in resource output', $name));
    }
}
