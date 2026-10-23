<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Script\Execution\InterfaceHook;
use Contena\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Script\Execution\ScriptLoader;
use Contena\Core\Framework\Script\ScriptException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(ScriptExecutor::class)]
class ScriptExecutorTest extends TestCase
{
    public function testThrowsIfHookIsInterfaceHook(): void
    {
        $scriptExecutor = new ScriptExecutor(
            static::createStub(ScriptLoader::class),
            static::createStub(ScriptTraces::class),
            static::createStub(ContainerInterface::class),
            static::createStub(ScriptEnvironmentFactory::class),
        );

        try {
            $scriptExecutor->execute(static::createStub(InterfaceHook::class));
        } catch (ScriptException $e) {
            static::assertSame(ScriptException::INTERFACE_HOOK_EXECUTION_NOT_ALLOWED, $e->getErrorCode());
        }
    }
}
