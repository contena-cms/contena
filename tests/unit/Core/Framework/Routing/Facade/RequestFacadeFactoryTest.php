<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing\Facade;

use Contena\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(RequestFacadeFactory::class)]
class RequestFacadeFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');
        $request->attributes->set('ct-original-request-uri', 'https://example.com/foo/bar');
        $stack = new RequestStack();
        $stack->push($request);

        $factory = new RequestFacadeFactory($stack);

        static::assertSame('request', $factory->getName());

        $script = new Script('foo', 'bar', new \DateTimeImmutable());
        $facade = $factory->factory(static::createStub(Hook::class), $script);

        static::assertSame('https://example.com/foo/bar', $facade->uri());
    }
}
