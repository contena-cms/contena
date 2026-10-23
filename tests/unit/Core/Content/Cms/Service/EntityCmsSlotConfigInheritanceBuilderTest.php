<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationEntity;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Group('store-api')]
#[CoversClass(EntityCmsSlotConfigInheritanceBuilder::class)]
class EntityCmsSlotConfigInheritanceBuilderTest extends TestCase
{
    public function testBuildMergesSlotConfigAlongExplicitParentLanguageChain(): void
    {
        $childLanguageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();
        $grandParentLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([
                $parentLanguageId,
                $grandParentLanguageId,
                null,
            ]),
        );

        $translations = new BlogTranslationCollection([
            $this->createTranslation($grandParentLanguageId, [
                'slot-a' => ['headline' => ['value' => 'grand-parent']],
            ]),
            $this->createTranslation($parentLanguageId, [
                'slot-a' => ['headline' => ['value' => 'parent']],
                'slot-b' => ['headline' => ['value' => 'parent']],
            ]),
            $this->createTranslation($childLanguageId, [
                'slot-b' => ['headline' => ['value' => 'child']],
            ]),
        ]);

        $result = $builder->build($translations, $this->createChannelContext($childLanguageId));

        static::assertSame([
            'slot-a' => ['headline' => ['value' => 'parent']],
            'slot-b' => ['headline' => ['value' => 'child']],
        ], $result);
    }

    public function testBuildRetainsParentLanguageFieldsWhenChildOverridesPartialSlot(): void
    {
        $childLanguageId = Uuid::randomHex();
        $parentLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([
                $parentLanguageId,
                null,
            ]),
        );

        $translations = new BlogTranslationCollection([
            $this->createTranslation($parentLanguageId, [
                'slot-a' => [
                    'headline' => ['value' => 'parent headline'],
                    'content' => ['value' => 'parent content'],
                ],
            ]),
            $this->createTranslation($childLanguageId, [
                'slot-a' => [
                    'content' => ['value' => 'child content'],
                ],
            ]),
        ]);

        $result = $builder->build($translations, $this->createChannelContext($childLanguageId));

        static::assertSame([
            'slot-a' => [
                'headline' => ['value' => 'parent headline'],
                'content' => ['value' => 'child content'],
            ],
        ], $result);
    }

    public function testBuildDoesNotMergeSystemLanguageWithoutExplicitParent(): void
    {
        $childLanguageId = Uuid::randomHex();
        $systemLanguageId = Uuid::randomHex();

        $builder = new EntityCmsSlotConfigInheritanceBuilder(
            $this->createConnectionWithParentLanguageIds([null]),
        );

        $translations = new BlogTranslationCollection([
            $this->createTranslation($systemLanguageId, [
                'slot-a' => ['headline' => ['value' => 'system']],
            ]),
        ]);

        $result = $builder->build($translations, $this->createChannelContext($childLanguageId));

        static::assertNull($result);
    }

    /**
     * @param array<string, array<string, mixed>> $slotConfig
     */
    private function createTranslation(string $languageId, array $slotConfig): BlogTranslationEntity
    {
        $translation = new BlogTranslationEntity();
        $translation->setUniqueIdentifier('translation-' . $languageId);
        $translation->setLanguageId($languageId);
        $translation->setSlotConfig($slotConfig);

        return $translation;
    }

    /**
     * @param list<?string> $parentLanguageIds
     */
    private function createConnectionWithParentLanguageIds(array $parentLanguageIds): Connection
    {
        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $results = array_map(function (?string $parentLanguageId): Result {
            $result = $this->createStub(Result::class);
            $result->method('fetchOne')->willReturn($parentLanguageId);

            return $result;
        }, $parentLanguageIds);

        $queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls(...$results);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    private function createChannelContext(string $languageId): ChannelContext
    {
        return Generator::generateChannelContext(new Context(
            new ChannelApiSource(Uuid::randomHex()),
            [],
            languageIdChain: [$languageId],
        ));
    }
}
