<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Category\Validation;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntryPointValidatorTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get(\sprintf('%s.repository', CategoryDefinition::ENTITY_NAME));
        $this->channelRepository = static::getContainer()->get(\sprintf('%s.repository', ChannelDefinition::ENTITY_NAME));
    }

    public function testChangeNavigationFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->channelRepository->update([
            [
                'id' => TestDefaults::CHANNEL,
                'navigationCategoryId' => $categoryId,
            ],
        ], $context);

        $this->expectException(WriteException::class);
        $this->categoryRepository->update([
            [
                'id' => $categoryId,
                'type' => CategoryDefinition::TYPE_LINK,
            ],
        ], $context);
    }

    public function testChangeServiceFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();

        $this->expectException(WriteException::class);
        $this->channelRepository->update([
            [
                'id' => TestDefaults::CHANNEL,
                'serviceCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_LINK,
                ],
            ],
        ], $context);
    }

    public function testChangeFooterValid(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->channelRepository->update([
            [
                'id' => TestDefaults::CHANNEL,
                'footerCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_PAGE,
                ],
            ],
        ], $context);

        /** @var CategoryEntity|null $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame(CategoryDefinition::TYPE_PAGE, $category->getType());
    }
}
