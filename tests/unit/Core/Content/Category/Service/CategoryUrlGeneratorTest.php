<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(CategoryUrlGenerator::class)]
class CategoryUrlGeneratorTest extends TestCase
{
    private const EXTERNAL_URL = 'https://contena.cn/';

    private CategoryUrlGenerator $urlGenerator;

    private Stub&EntityRouteResolver $entityRouteResolver;

    private ChannelEntity $channel;

    protected function setUp(): void
    {
        $this->entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $this->urlGenerator = new CategoryUrlGenerator($this->entityRouteResolver);
        $this->entityRouteResolver->method('generateSeoUrlPlaceholder')->willReturnArgument(0);
        $this->channel = new ChannelEntity();
        $this->channel->setTypeId(Defaults::CHANNEL_TYPE_FRONTEND);
        $this->channel->setNavigationCategoryId(Uuid::randomHex());
    }

    public function testPage(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        static::assertSame(CategoryDefinition::ENTITY_NAME, $this->urlGenerator->generate($category, $this->channel));
    }

    public function testFolder(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_FOLDER);

        static::assertNull($this->urlGenerator->generate($category, $this->channel));
    }

    #[DataProvider('dataProviderLinkTypes')]
    public function testLinkType(?string $type, string $route): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType($type);
        $category->addTranslated('linkType', $type);

        static::assertNull($this->urlGenerator->generate($category, $this->channel));

        $category->setExternalLink(self::EXTERNAL_URL);
        $category->addTranslated('externalLink', $category->getExternalLink());
        $category->setInternalLink(Uuid::randomHex());
        $category->addTranslated('internalLink', $category->getInternalLink());

        static::assertSame($route, $this->urlGenerator->generate($category, $this->channel));
    }

    /**
     * @return list<list<string|null>>
     */
    public static function dataProviderLinkTypes(): array
    {
        return [
            [CategoryDefinition::LINK_TYPE_BLOG, BlogDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_CATEGORY, CategoryDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_LANDING_PAGE, LandingPageDefinition::ENTITY_NAME],
            [CategoryDefinition::LINK_TYPE_EXTERNAL, self::EXTERNAL_URL],
            [null, self::EXTERNAL_URL],
        ];
    }
}
