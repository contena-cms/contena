<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testLoad(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(9, $manifests);
        static::assertInstanceOf(Manifest::class, $manifests['minimal']);
    }

    public function testLoadIgnoresInvalid(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/invalid');

        $manifests = $appLoader->load();

        static::assertCount(0, $manifests);
    }

    public function testLoadCombinesFolders(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(9, $manifests);
        foreach ($manifests as $manifest) {
            static::assertInstanceOf(Manifest::class, $manifest);
        }
    }
}
