<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;
use Contena\Core\Framework\FrameworkException;

/**
 * @internal
 */
#[CoversClass(EntityWriteResultCollection::class)]
class EntityWriteResultCollectionTest extends TestCase
{
    public function testFiltersByOperation(): void
    {
        $insert = new EntityWriteResult('insert-id', [], 'entity', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', [], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $delete = new EntityWriteResult('delete-id', [], 'entity', EntityWriteResult::OPERATION_DELETE);

        $results = new EntityWriteResultCollection([$insert, $update, $delete]);

        static::assertSame([$insert, $update], $results->only(
            EntityWriteResult::OPERATION_INSERT,
            EntityWriteResult::OPERATION_UPDATE,
        )->getElements());
        static::assertCount(3, $results);
        static::assertSame('dal_entity_write_result_collection', $results->getApiAlias());
    }

    public function testFiltersWhenAnyPayloadPropertyIsPresent(): void
    {
        $withNull = new EntityWriteResult('null-id', ['active' => null], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $withName = new EntityWriteResult('name-id', ['name' => 'Example'], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $withoutMatch = new EntityWriteResult('other-id', ['position' => 10], 'entity', EntityWriteResult::OPERATION_UPDATE);

        $results = new EntityWriteResultCollection([$withNull, $withName, $withoutMatch]);

        static::assertSame([$withNull, $withName], $results->withPayloadProperties('active', 'name')->getElements());
    }

    public function testFiltersCanBeChainedWithoutChangingOriginalCollection(): void
    {
        $matchingUpdate = new EntityWriteResult('matching-id', ['active' => true], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $otherUpdate = new EntityWriteResult('other-id', ['position' => 10], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $matchingInsert = new EntityWriteResult('insert-id', ['active' => true], 'entity', EntityWriteResult::OPERATION_INSERT);
        $results = new EntityWriteResultCollection([$matchingUpdate, $otherUpdate, $matchingInsert]);

        $filtered = $results
            ->only(EntityWriteResult::OPERATION_UPDATE)
            ->withPayloadProperties('active');

        static::assertSame([$matchingUpdate], $filtered->getElements());
        static::assertSame([$matchingUpdate, $otherUpdate, $matchingInsert], $results->getElements());
    }

    public function testEmptyFiltersMatchNoResults(): void
    {
        $results = new EntityWriteResultCollection([
            new EntityWriteResult('entity-id', ['active' => true], 'entity', EntityWriteResult::OPERATION_UPDATE),
        ]);

        static::assertTrue($results->only()->isEmpty());
        static::assertTrue($results->withPayloadProperties()->isEmpty());
        static::assertSame([], $results->only('unknown')->getPrimaryKeys());
    }

    public function testReturnsStringAndCompositePrimaryKeys(): void
    {
        $stringResults = new EntityWriteResultCollection([
            new EntityWriteResult('entity-id', [], 'entity', EntityWriteResult::OPERATION_UPDATE),
        ]);
        $compositeResults = new EntityWriteResultCollection([
            new EntityWriteResult(
                ['entityId' => 'entity-id', 'languageId' => 'language-id'],
                [],
                'entity_translation',
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ]);

        static::assertSame(['entity-id'], $stringResults->getPrimaryKeys());
        static::assertSame([
            ['entityId' => 'entity-id', 'languageId' => 'language-id'],
        ], $compositeResults->getPrimaryKeys());
    }

    public function testRejectsInvalidElements(): void
    {
        $results = new EntityWriteResultCollection();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type */
        $results->add(new \stdClass());
    }
}
