<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ScriptResponseFactoryFacadeHookFactory::class)]
class ScriptResponseFactoryFacadeHookFactoryTest extends TestCase
{
    #[TestDox('getName returns the documented "response" script-service identifier')]
    public function testGetNameIsResponse(): void
    {
        static::assertSame('response', $this->buildFactory()->getName());
    }

    #[TestDox('factory() builds a usable core response facade for a hook without a ChannelContext')]
    public function testFactoryBuildsCoreResponseFacade(): void
    {
        $hook = new class(Context::createDefaultContext()) extends Hook {
            public function getName(): string
            {
                return 'test.hook';
            }

            public static function getServiceIds(): array
            {
                return [];
            }
        };

        $facade = $this->buildFactory()->factory($hook, static::createStub(Script::class));

        $response = $facade->json(['ok' => true], Response::HTTP_CREATED);
        static::assertSame(['ok' => true], $response->getBody()->all());
        static::assertSame(Response::HTTP_CREATED, $response->getCode());
    }

    #[TestDox('factory() keeps the deprecated render BC path when Frontend and ChannelContext are available')]
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFactoryBuildsCoreResponseFacadeWithLegacyRenderSupport(): void
    {
        $hook = $this->createChannelContextAwareHook(static::createStub(ChannelContext::class));

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderFrontendForScript')
            ->with('@Frontend/foo.html.twig', [])
            ->willReturn(new Response('ok'));

        $facade = $this->buildFactory($scriptController)->factory($hook, static::createStub(Script::class));

        $response = $facade->render('@Frontend/foo.html.twig');

        static::assertSame('ok', $response->getInner()?->getContent());
    }

    private function buildFactory(?ScriptController $scriptController = null): ScriptResponseFactoryFacadeHookFactory
    {
        return new ScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $scriptController,
        );
    }

    private function createChannelContextAwareHook(ChannelContext $channelContext): Hook&ChannelContextAware
    {
        return new class(Context::createDefaultContext(), $channelContext) extends Hook implements ChannelContextAware {
            public function __construct(
                Context $context,
                private readonly ChannelContext $channelContext,
            ) {
                parent::__construct($context);
            }

            public function getName(): string
            {
                return 'test.hook';
            }

            public static function getServiceIds(): array
            {
                return [];
            }

            public function getChannelContext(): ChannelContext
            {
                return $this->channelContext;
            }
        };
    }
}
