<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Twig\Extension\BuildBreadcrumbExtension;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed
 */
#[CoversClass(BuildBreadcrumbExtension::class)]
class BuildBreadcrumbExtensionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFunctions(): void
    {
        $functions = $this->getBuildBreadcrumbExtension()->getFunctions();

        static::assertSame('sw_breadcrumb_full', $functions[0]->getName());
        static::assertSame('sw_breadcrumb_full_by_id', $functions[1]->getName());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbNoSeoBreadCrumb(): void
    {
        $channelContext = Generator::generateChannelContext();

        $breadCrumb = $this->getBuildBreadcrumbExtension()
            ->getFullBreadcrumb([], new CategoryEntity(), $channelContext);

        static::assertSame([], $breadCrumb);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbWithEmptySeoBreadCrumb(): void
    {
        $channelContext = Generator::generateChannelContext();

        $categoryBreadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([]);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder)
            ->getFullBreadcrumb([], new CategoryEntity(), $channelContext);

        static::assertSame([], $breadCrumb);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbWithSeoBreadCrumb(): void
    {
        $channelContext = Generator::generateChannelContext();
        $categoryId = Uuid::randomHex();
        $notConsideredCategoryId = Uuid::randomHex();

        $categoryBreadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([$categoryId => 'Home', $notConsideredCategoryId => 'Not considered']);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder, $categoryId)
            ->getFullBreadcrumb([], new CategoryEntity(), $channelContext);

        static::assertArrayHasKey($categoryId, $breadCrumb);
        static::assertInstanceOf(ChannelCategoryEntity::class, $breadCrumb[$categoryId]);
        static::assertArrayNotHasKey($notConsideredCategoryId, $breadCrumb);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbUsesChannelContextFallback(): void
    {
        $channelContext = Generator::generateChannelContext();
        $category = new CategoryEntity();

        $categoryBreadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder
            ->expects($this->once())
            ->method('build')
            ->with($category, $channelContext->getChannel())
            ->willReturn([]);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder)
            ->getFullBreadcrumb([
                'context' => Context::createDefaultContext(),
                'channelContext' => $channelContext,
            ], $category, Context::createDefaultContext());

        static::assertSame([], $breadCrumb);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbByIdWithNonExistingCategoryId(): void
    {
        $channelContext = Generator::generateChannelContext();

        $breadCrumb = $this->getBuildBreadcrumbExtension()
            ->getFullBreadcrumbById([], Uuid::randomHex(), $channelContext);

        static::assertSame([], $breadCrumb);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFullBreadcrumbById(): void
    {
        $channelContext = Generator::generateChannelContext();
        $categoryId = Uuid::randomHex();
        $notConsideredCategoryId = Uuid::randomHex();

        $categoryBreadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $categoryBreadcrumbBuilder->method('build')->willReturn([$categoryId => 'Home', $notConsideredCategoryId => 'Not considered']);

        $breadCrumb = $this->getBuildBreadcrumbExtension($categoryBreadcrumbBuilder, $categoryId)
            ->getFullBreadcrumbById([], $categoryId, $channelContext);

        static::assertArrayHasKey($categoryId, $breadCrumb);
        static::assertInstanceOf(ChannelCategoryEntity::class, $breadCrumb[$categoryId]);
        static::assertArrayNotHasKey($notConsideredCategoryId, $breadCrumb);
    }

    private function getBuildBreadcrumbExtension(?CategoryBreadcrumbBuilder $categoryBreadcrumbBuilder = null, ?string $categoryId = null): BuildBreadcrumbExtension
    {
        $categoryBreadcrumbBuilder ??= static::createStub(CategoryBreadcrumbBuilder::class);

        $categories = new CategoryCollection();
        if ($categoryId !== null) {
            $category = new ChannelCategoryEntity();
            $category->setUniqueIdentifier($categoryId);
            $category->internalSetEntityData(CategoryDefinition::ENTITY_NAME, new FieldVisibility([]));
            $categories->add($category);
        }

        $entitySearchResult = new EntitySearchResult(
            CategoryDefinition::ENTITY_NAME,
            1,
            $categories,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        /** @var StaticChannelRepository<EntityCollection<ChannelCategoryEntity>> $channelCategoryRepository */
        $channelCategoryRepository = new StaticChannelRepository([
            $entitySearchResult, clone $entitySearchResult,
        ]);

        $categoryRepository = new StaticEntityRepository([]);

        return new BuildBreadcrumbExtension($categoryBreadcrumbBuilder, $channelCategoryRepository, $categoryRepository);
    }
}
