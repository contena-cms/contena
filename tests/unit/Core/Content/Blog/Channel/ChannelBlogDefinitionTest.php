<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelBlogDefinition::class)]
class ChannelBlogDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(ChannelBlogEntity::class, $definition->getEntityClass());
        static::assertSame(ChannelBlogCollection::class, $definition->getCollectionClass());
    }

    public function testDefinesTheRuntimeCalculationFields(): void
    {
        $fields = $this->createDefinition()->getFields();

        foreach (['calculatedPrice', 'calculatedPrices', 'calculatedMaxPurchase', 'calculatedCheapestPrice', 'isNew', 'cheapestPrice', 'cheapestPriceContainer', 'sortedProperties', 'measurements'] as $field) {
            static::assertNotNull($fields->get($field), $field . ' must be defined');
        }
    }

    public function testProcessCriteriaAddsTheAvailableFilterAndDefaultAssociations(): void
    {
        $criteria = new Criteria();

        $this->createDefinition()->processCriteria($criteria, static::createStub(ChannelContext::class));

        $availableFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof BlogAvailableFilter);
        static::assertCount(1, $availableFilters);

        foreach (['prices', 'unit', 'deliveryTime', 'cover', 'tax'] as $association) {
            static::assertTrue($criteria->hasAssociation($association), $association . ' association expected');
        }
    }

    public function testProcessCriteriaKeepsAnExistingAvailableFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new BlogAvailableFilter('sales-channel-id'));

        $this->createDefinition()->processCriteria($criteria, static::createStub(ChannelContext::class));

        $availableFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof BlogAvailableFilter);
        static::assertCount(1, $availableFilters);
    }

    public function testProcessCriteriaFiltersBlogReviewsToActiveOnes(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('blogReviews');

        $this->createDefinition()->processCriteria($criteria, static::createStub(ChannelContext::class));

        $filters = $criteria->getAssociation('blogReviews')->getFilters();
        static::assertCount(1, $filters);
    }

    public function testProcessCriteriaSkipsAssociationSetupBelowRootNestingLevel(): void
    {
        $criteria = new Criteria(nestingLevel: 1);

        $this->createDefinition()->processCriteria($criteria, static::createStub(ChannelContext::class));

        static::assertFalse($criteria->hasAssociation('prices'));
    }

    #[DataProvider('nestingLevelProvider')]
    public function testProcessCriteriaFiltersInactiveReviewsOnEveryNestingLevel(int $nestingLevel): void
    {
        $definition = new ChannelBlogDefinition();
        $criteria = new Criteria(nestingLevel: $nestingLevel);
        $criteria->addAssociation('blogReviews');
        $context = Generator::generateChannelContext(overrides: ['user' => null]);

        $definition->processCriteria($criteria, $context);

        static::assertEquals(
            [new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)])],
            $criteria->getAssociation('blogReviews')->getFilters()
        );
    }

    #[DataProvider('nestingLevelProvider')]
    public function testProcessCriteriaAllowsOwnReviewsOnEveryNestingLevel(int $nestingLevel): void
    {
        $definition = new ChannelBlogDefinition();
        $criteria = new Criteria(nestingLevel: $nestingLevel);
        $criteria->addAssociation('blogReviews');

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $context = Generator::generateChannelContext(user: $user);

        $definition->processCriteria($criteria, $context);

        static::assertEquals(
            [new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('status', true),
                new EqualsFilter('userId', $user->getId()),
            ])],
            $criteria->getAssociation('blogReviews')->getFilters()
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nestingLevelProvider(): iterable
    {
        yield 'root level' => [Criteria::ROOT_NESTING_LEVEL];
        yield 'first association level' => [1];
        yield 'second association level' => [2];
    }

    private function createDefinition(): ChannelBlogDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ChannelBlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(ChannelBlogDefinition::ENTITY_NAME);
        static::assertInstanceOf(ChannelBlogDefinition::class, $definition);

        return $definition;
    }
}
