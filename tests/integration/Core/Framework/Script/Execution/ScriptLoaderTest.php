<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Script\Execution;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\ScriptLoader;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class ScriptLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testGetScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $loader = static::getContainer()->get(ScriptLoader::class);

        static::assertCount(
            1,
            $loader->get('include-case')
        );
        static::assertCount(
            2,
            $loader->get('multi-script-case')
        );
        static::assertCount(
            0,
            $loader->get('include')
        );
    }

    public function testGetInactiveScripts(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures', false);

        $loader = static::getContainer()->get(ScriptLoader::class);

        static::assertCount(1, $loader->get('include-case'));
        static::assertFalse($loader->get('include-case')[0]->isActive());

        static::assertCount(2, $loader->get('multi-script-case'));
        static::assertFalse($loader->get('multi-script-case')[0]->isActive());
        static::assertFalse($loader->get('multi-script-case')[1]->isActive());

        static::assertCount(0, $loader->get('include'));
    }
}
