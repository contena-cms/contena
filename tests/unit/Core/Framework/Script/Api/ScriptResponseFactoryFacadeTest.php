<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Contena\Core\Framework\Script\ScriptException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ScriptResponseFactoryFacade::class)]
class ScriptResponseFactoryFacadeTest extends TestCase
{
    #[TestDox('json() builds a ScriptResponse with the given body and status code')]
    public function testJsonReturnsScriptResponseWithBody(): void
    {
        $facade = $this->buildFacade();

        $response = $facade->json(['foo' => 'bar'], Response::HTTP_CREATED);

        static::assertSame(Response::HTTP_CREATED, $response->getCode());
        static::assertSame(['foo' => 'bar'], $response->getBody()->all());
    }

    #[TestDox('redirect() generates the URL via router and wraps it in a RedirectResponse')]
    public function testRedirectUsesRouterAndWrapsInRedirectResponse(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', ['foo' => 'bar'])
            ->willReturn('/home?foo=bar');

        $facade = $this->buildFacade($router);

        $response = $facade->redirect('frontend.home.page', ['foo' => 'bar']);

        $inner = $response->getInner();
        static::assertInstanceOf(RedirectResponse::class, $inner);
        static::assertSame('/home?foo=bar', $inner->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $response->getCode());
    }

    #[TestDox('render() on the core facade is deprecated and throws because rendering needs the Frontend bundle')]
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRenderThrowsFrontendBundleMissing(): void
    {
        $facade = $this->buildFacade(channelContext: static::createStub(ChannelContext::class));

        $this->expectExceptionObject(
            ScriptException::frontendBundleMissingForHookMethod(ScriptResponseFactoryFacade::class . '::render')
        );

        $facade->render('@Frontend/foo.html.twig');
    }

    #[TestDox('render() keeps the deprecated Frontend rendering BC path when its dependencies are available')]
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRenderDelegatesToScriptControllerForLegacyBc(): void
    {
        $rendered = new Response('rendered frontend html', Response::HTTP_ACCEPTED);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderFrontendForScript')
            ->with('@Frontend/detail.html.twig', ['page' => 'data'])
            ->willReturn($rendered);

        $facade = $this->buildFacade(
            scriptController: $scriptController,
            channelContext: static::createStub(ChannelContext::class),
        );

        $response = $facade->render('@Frontend/detail.html.twig', ['page' => 'data']);

        static::assertSame($rendered, $response->getInner());
        static::assertSame(Response::HTTP_ACCEPTED, $response->getCode());
    }

    private function buildFacade(
        ?RouterInterface $router = null,
        ?ScriptController $scriptController = null,
        ?ChannelContext $channelContext = null,
    ): ScriptResponseFactoryFacade {
        return new ScriptResponseFactoryFacade(
            $router ?? static::createStub(RouterInterface::class),
            $scriptController,
            $channelContext,
        );
    }
}
