<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\Extension\EntitySeoUrlFunctionExtension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(EntitySeoUrlFunctionExtension::class)]
class EntitySeoUrlFunctionExtensionTest extends TestCase
{
    private EntityRouteResolver&MockObject $entityRouteResolver;

    private EntitySeoUrlFunctionExtension $extension;

    protected function setUp(): void
    {
        $this->entityRouteResolver = $this->createMock(EntityRouteResolver::class);
        $this->extension = new EntitySeoUrlFunctionExtension($this->entityRouteResolver);
    }

    public function testGetFunctionsExposesEntitySeoUrl(): void
    {
        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        $functions = $this->extension->getFunctions();

        static::assertCount(1, $functions);
        static::assertInstanceOf(TwigFunction::class, $functions[0]);
        static::assertSame('entitySeoUrl', $functions[0]->getName());
        static::assertTrue($functions[0]->needsContext());
    }

    public function testForwardsNullChannelTypeIdWhenContextIsMissing(): void
    {
        $primaryKey = '019fff00000000000000000000000000';

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('blog', $primaryKey, null)
            ->willReturn('entity-url');

        static::assertSame('entity-url', $this->extension->entitySeoUrl([], 'blog', $primaryKey));
    }

    public function testForwardsChannelTypeIdFromContext(): void
    {
        $primaryKey = '019fff00000000000000000000000000';
        $channel = static::createStub(ChannelEntity::class);
        $channel->method('getTypeId')->willReturn(Defaults::CHANNEL_TYPE_API);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('blog', $primaryKey, Defaults::CHANNEL_TYPE_API)
            ->willReturn('headless-url');

        static::assertSame(
            'headless-url',
            $this->extension->entitySeoUrl(['channelContext' => $channelContext], 'blog', $primaryKey),
        );
    }
}
