<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Category\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Test\Category\CategoryBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CategorySubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testChannelCategoryLoadedAssignsSeoUrl(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        static::getContainer()->get('category.repository')->create(
            [(new CategoryBuilder($ids, 'c.1'))->build()],
            $context
        );

        $criteria = new Criteria([$ids->get('c.1')]);

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $searchResult = static::getContainer()->get('channel.category.repository')
            ->search($criteria, $channelContext)->getEntities();

        $category = $searchResult->get($ids->get('c.1'));

        static::assertInstanceOf(ChannelCategoryEntity::class, $category);
        static::assertSame("124c71d524604ccbad6042edce3ac799/store-api/category/{$ids->get('c.1')}#", $category->getSeoUrl());
    }
}
