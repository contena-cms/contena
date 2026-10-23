<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Api\ScriptApiRoute;
use Contena\Core\Framework\Script\Api\ScriptResponseEncoder;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptAppInformation;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Script\Execution\ScriptLoader;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ScriptApiRoute::class)]
class ScriptApiRouteTest extends TestCase
{
    private ScriptLoader&Stub $loader;

    private ScriptExecutor&MockObject $executor;

    private ScriptApiRoute $route;

    protected function setUp(): void
    {
        $this->loader = static::createStub(ScriptLoader::class);
        $this->executor = $this->createMock(ScriptExecutor::class);

        $encoder = static::createStub(ScriptResponseEncoder::class);
        $encoder->method('encodeToSymfonyResponse')->willReturn(new Response());

        $this->route = new ScriptApiRoute($this->executor, $this->loader, $encoder);
    }

    public function testShopOwnerScriptsAreForbiddenEvenWithTheAppAllPrivilege(): void
    {
        $this->loader->method('get')->willReturn([
            new Script('api-my-hook', '', new \DateTimeImmutable()),
        ]);

        $this->executor->expects($this->never())->method('execute');

        $this->expectExceptionObject(new PermissionDeniedException());

        $this->route->execute('my-hook', new Request(), $this->context(['app.all']));
    }

    public function testAppScriptsAreExecutedWithTheAppAllPrivilege(): void
    {
        $this->loader->method('get')->willReturn([
            new Script(
                'api-my-hook',
                '',
                new \DateTimeImmutable(),
                new ScriptAppInformation(Uuid::randomHex(), 'MyApp', '1.0.0', Uuid::randomHex())
            ),
        ]);

        $this->executor->expects($this->once())->method('execute');

        $response = $this->route->execute('my-hook', new Request(), $this->context(['app.all']));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param list<string> $permissions
     */
    private function context(array $permissions): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions($permissions);

        return new Context($source);
    }
}
