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
        $insert = $this->createWriteResult('insert-id', [], EntityWriteResult::OPERATION_INSERT);
        $update = $this->createWriteResult('update-id', [], EntityWriteResult::OPERATION_UPDATE);
        $delete = $this->createWriteResult('delete-id', [], EntityWriteResult::OPERATION_DELETE);

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
        $withNull = $this->createWriteResult('null-id', ['active' => null], EntityWriteResult::OPERATION_UPDATE);
        $withName = $this->createWriteResult('name-id', ['name' => 'Example'], EntityWriteResult::OPERATION_UPDATE);
        $withoutMatch = $this->createWriteResult('stock-id', ['stock' => 10], EntityWriteResult::OPERATION_UPDATE);

        $results = new EntityWriteResultCollection([$withNull, $withName, $withoutMatch]);

        static::assertSame([$withNull, $withName], $results->withPayloadProperties('active', 'name')->getElements());
    }

    public function testFiltersCanBeChainedWithoutChangingOriginalCollection(): void
    {
        $matchingUpdate = $this->createWriteResult('matching-id', ['active' => true], EntityWriteResult::OPERATION_UPDATE);
        $otherUpdate = $this->createWriteResult('other-id', ['stock' => 10], EntityWriteResult::OPERATION_UPDATE);
        $matchingInsert = $this->createWriteResult('insert-id', ['active' => true], EntityWriteResult::OPERATION_INSERT);
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
            new EntityWriteResult('blog-id', ['active' => true], 'blog', EntityWriteResult::OPERATION_UPDATE),
        ]);

        static::assertTrue($results->only()->isEmpty());
        static::assertTrue($results->withPayloadProperties()->isEmpty());
        static::assertSame([], $results->only('unknown')->getPrimaryKeys());
    }

    public function testReturnsPrimaryKeys(): void
    {
        $stringResults = new EntityWriteResultCollection([
            new EntityWriteResult('blog-id', [], 'blog', EntityWriteResult::OPERATION_UPDATE),
        ]);
        $compositeResults = new EntityWriteResultCollection([
            new EntityWriteResult(
                ['blogId' => 'blog-id', 'versionId' => 'version-id'],
                [],
                'blog_translation',
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ]);

        static::assertSame(['blog-id'], $stringResults->getPrimaryKeys());
        static::assertSame([
            ['blogId' => 'blog-id', 'versionId' => 'version-id'],
        ], $compositeResults->getPrimaryKeys());
    }

    public function testRejectsInvalidElements(): void
    {
        $results = new EntityWriteResultCollection();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type */
        $results->add(new \stdClass());
    }

    /**
     * @param array<string, mixed> $payload
     * @param EntityWriteResult::OPERATION_* $operation
     */
    private function createWriteResult(string $primaryKey, array $payload, string $operation): EntityWriteResult
    {
        return new EntityWriteResult($primaryKey, $payload, 'blog', $operation);
    }
}
