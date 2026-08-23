<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(EnvTestBehaviour::class)]
class EnvTestBehaviourTest extends TestCase
{
    use EnvTestBehaviour;

    public function testResetRemovesOriginallyUndefinedVariable(): void
    {
        $name = 'CONTENA_ENV_TEST_BEHAVIOUR_ABSENT';
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);

        $this->setEnvVars([$name => 'configured']);
        $this->resetEnvVars();

        static::assertArrayNotHasKey($name, $_SERVER);
        static::assertArrayNotHasKey($name, $_ENV);
        static::assertFalse(getenv($name));
    }
}
