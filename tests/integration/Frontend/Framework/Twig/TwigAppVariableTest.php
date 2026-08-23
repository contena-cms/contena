<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Frontend\Framework\Twig\TwigAppVariable;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class TwigAppVariableTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRequestCycleDoesNotTouchActualRequest(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL']);

        static::assertTrue($browser->getRequest()->server->has('SERVER_PROTOCOL'));
    }

    public function testRequestGetsCloned(): void
    {
        $originalRequest = new Request();
        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($originalRequest);

        $app = new TwigAppVariable($appVariable);

        static::assertNotSame($originalRequest, $app->getRequest());
    }

    public function testClonedRequestLosesServerVariablesOutsideAllowList(): void
    {
        $originalRequest = new Request();
        $originalRequest->server->set('good', '1');
        $originalRequest->server->set('bad', '1');
        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($originalRequest);

        $appRequest = new TwigAppVariable($appVariable, ['good'])->getRequest();

        static::assertNotNull($appRequest);
        static::assertTrue($originalRequest->server->has('bad'));
        static::assertTrue($originalRequest->server->has('good'));
        static::assertTrue($appRequest->server->has('good'));
        static::assertFalse($appRequest->server->has('bad'));
    }

    public function testHttpsRequestKeepsSecureScheme(): void
    {
        $originalRequest = new Request();
        $originalRequest->server->set('HTTPS', '1');
        $originalRequest->server->set('SERVER_NAME', 'localhost');
        $originalRequest->server->set('SERVER_PORT', '443');
        $appVariable = $this->createMock(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($originalRequest);

        $appRequest = new TwigAppVariable($appVariable, ['https', 'server_name', 'server_port'])->getRequest();

        static::assertNotNull($appRequest);
        static::assertTrue($appRequest->isSecure());
        static::assertSame('https://localhost', $appRequest->getSchemeAndHttpHost());
    }
}
