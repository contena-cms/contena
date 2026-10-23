<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Controller\ScriptController;
use Contena\Frontend\Framework\Script\Api\FrontendHook;
use Contena\Frontend\Framework\Script\Api\FrontendScriptResponseFactoryFacade;
use Contena\Frontend\Framework\Script\Api\FrontendScriptResponseFactoryFacadeHookFactory;
use Contena\Frontend\Page\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(FrontendScriptResponseFactoryFacadeHookFactory::class)]
class FrontendScriptResponseFactoryFacadeHookFactoryTest extends TestCase
{
    #[TestDox('getName returns the documented "response" script-service identifier')]
    public function testGetNameIsResponse(): void
    {
        static::assertSame('response', $this->buildFactory()->getName());
    }

    #[TestDox('factory() builds the Frontend facade and forwards the hook ChannelContext into render()')]
    public function testFactoryBuildsFrontendFacadeWithChannelContext(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $hook = new FrontendHook('test-hook', [], [], new Page(), $channelContext);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderFrontendForScript')
            ->with('@Frontend/foo.html.twig', [])
            ->willReturn(new Response('ok'));

        $facade = $this->buildFactory($scriptController)->factory($hook, static::createStub(Script::class));

        static::assertInstanceOf(FrontendScriptResponseFactoryFacade::class, $facade);
        // render() succeeding proves the ChannelContext was forwarded (otherwise it throws).
        $facade->render('@Frontend/foo.html.twig');
    }

    private function buildFactory(?ScriptController $scriptController = null): FrontendScriptResponseFactoryFacadeHookFactory
    {
        return new FrontendScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $scriptController ?? static::createStub(ScriptController::class),
        );
    }
}
