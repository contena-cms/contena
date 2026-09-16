<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Script\Api;

use Contena\Frontend\Controller\ScriptController;
use Contena\Frontend\Framework\Script\Api\FrontendScriptResponseFactoryFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(FrontendScriptResponseFactoryFacade::class)]
class FrontendScriptResponseFactoryFacadeTest extends TestCase
{
    #[TestDox('render() delegates to ScriptController and wraps the rendered response')]
    public function testRenderDelegatesToScriptController(): void
    {
        $rendered = new Response('rendered frontend html', Response::HTTP_ACCEPTED);
        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderFrontendForScript')
            ->with('@Frontend/detail.html.twig', ['page' => 'data'])
            ->willReturn($rendered);

        $facade = new FrontendScriptResponseFactoryFacade(
            static::createStub(RouterInterface::class),
            $scriptController,
        );

        $response = $facade->render('@Frontend/detail.html.twig', ['page' => 'data']);

        static::assertSame($rendered, $response->getInner());
        static::assertSame(Response::HTTP_ACCEPTED, $response->getCode());
    }
}
