<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(DefaultCategoryLevelLoader::class)]
class DefaultCategoryLevelLoaderTest extends TestCase
{
    private DefaultCategoryLevelLoader $categoryLevelLoader;

    /**
     * @var MockObject&ChannelRepository<CategoryCollection>
     */
    private MockObject&ChannelRepository $categoryRepository;

    private Stub&ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(ChannelRepository::class);
        $this->channelContext = static::createStub(ChannelContext::class);

        $this->categoryLevelLoader = new DefaultCategoryLevelLoader(
            $this->categoryRepository
        );
    }

    public function testLoadLevels(): void
    {
        $rootId = 'non-navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $channel = (new ChannelEntity())->assign([
            'navigationCategoryId' => 'different-id',
        ]);

        $this->channelContext->method('getChannel')
            ->willReturn($channel);

        $expectedCollection = new CategoryCollection();

        $this->categoryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                0,
                $expectedCollection,
                null,
                $criteria,
                $this->channelContext->getContext()
            ));

        $result = $this->categoryLevelLoader->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );

        static::assertSame($expectedCollection, $result);
    }
}
