<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\Framework\Adapter\Twig\Extension\CategoryUrlExtension;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Generator;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 - Will be removed
 */
#[CoversClass(CategoryUrlExtension::class)]
class CategoryUrlExtensionTest extends TestCase
{
    public function testGetCategoryUrlUsesChannelContextFallback(): void
    {
        $category = new CategoryEntity();
        $channelContext = Generator::generateChannelContext();

        $categoryUrlGenerator = $this->createMock(AbstractCategoryUrlGenerator::class);
        $categoryUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($category, $channelContext->getChannel())
            ->willReturn('/navigation');

        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            $categoryUrlGenerator
        );

        static::assertSame('/navigation', $extension->getCategoryUrl([
            'context' => Context::createDefaultContext(),
            'channelContext' => $channelContext,
        ], $category));
    }

    #[DataProvider('linkNewTabProvider')]
    public function testIsLinkNewTabUsesCategorySetting(bool $linkNewTab): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setTranslated(['linkNewTab' => $linkNewTab]);

        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            static::createStub(AbstractCategoryUrlGenerator::class)
        );

        static::assertSame($linkNewTab, $extension->isLinkNewTab($category));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function linkNewTabProvider(): iterable
    {
        yield 'opens link in a new tab' => [true];
        yield 'opens link in the same tab' => [false];
    }
}
