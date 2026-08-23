<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\FrontendCategoryUrlGenerator;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(FrontendCategoryUrlGenerator::class)]
class FrontendCategoryUrlGeneratorTest extends TestCase
{
    public function testGenerateHomePageLinkUsesRouter(): void
    {
        $navigationCategoryId = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page')
            ->willReturn('/');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->never())->method('generate');

        $generator = new FrontendCategoryUrlGenerator($decorated, $router);

        $category = $this->createCategoryLink($navigationCategoryId);

        static::assertSame('/', $generator->generate($category, $this->createChannel($navigationCategoryId)));
    }

    public function testGenerateDelegatesWhenCategoryIsNotALink(): void
    {
        $navigationCategoryId = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->once())
            ->method('generate')
            ->willReturn('DELEGATED');

        $generator = new FrontendCategoryUrlGenerator($decorated, $router);

        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_PAGE);
        $category->setId($navigationCategoryId);

        static::assertSame('DELEGATED', $generator->generate($category, $this->createChannel($navigationCategoryId)));
    }

    public function testGenerateDelegatesForNonCategoryLinkType(): void
    {
        $internalLink = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->once())
            ->method('generate')
            ->willReturn('DELEGATED');

        $generator = new FrontendCategoryUrlGenerator($decorated, $router);

        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_BLOG);
        $category->addTranslated('internalLink', $internalLink);

        static::assertSame('DELEGATED', $generator->generate($category, $this->createChannel($internalLink)));
    }

    public function testGetDecoratedReturnsInner(): void
    {
        $decorated = static::createStub(AbstractCategoryUrlGenerator::class);
        $generator = new FrontendCategoryUrlGenerator($decorated, static::createStub(RouterInterface::class));

        static::assertSame($decorated, $generator->getDecorated());
    }

    private function createCategoryLink(string $internalLink): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->addTranslated('internalLink', $internalLink);

        return $category;
    }

    private function createChannel(string $navigationCategoryId): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setNavigationCategoryId($navigationCategoryId);

        return $channel;
    }
}
