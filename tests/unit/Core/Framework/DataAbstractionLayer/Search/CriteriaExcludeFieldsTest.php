<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[CoversClass(Criteria::class)]
class CriteriaExcludeFieldsTest extends TestCase
{
    public function testExcludeFieldsStoresAndMerges(): void
    {
        $criteria = new Criteria();
        $criteria->excludeFields(['description'])->excludeFields(['keywords']);

        static::assertSame(['description', 'keywords'], $criteria->getExcludedFields());
        static::assertSame([], $criteria->getFields());
    }

    public function testCloneForReadKeepsExcludedFields(): void
    {
        $criteria = new Criteria()->excludeFields(['description']);

        static::assertSame(['description'], $criteria->cloneForRead(['test-id'])->getExcludedFields());
    }

    public function testExcludeFieldsThrowsWhenAllowlistFieldsAlreadySet(): void
    {
        $criteria = new Criteria()->addFields(['name']);

        $this->expectException(DataAbstractionLayerException::class);
        $criteria->excludeFields(['description']);
    }

    public function testAddFieldsThrowsWhenExcludedFieldsAlreadySet(): void
    {
        $criteria = new Criteria()->excludeFields(['description']);

        $this->expectException(DataAbstractionLayerException::class);
        $criteria->addFields(['name']);
    }

    public function testAddFieldAddsOneAllowlistField(): void
    {
        $criteria = new Criteria()->addField('name');

        static::assertSame(['name'], $criteria->getFields());
    }

    public function testResetFieldsClearsAllowlistAndDenylistFields(): void
    {
        $criteria = new Criteria()->addFields(['name']);
        $criteria->resetFields();

        static::assertSame([], $criteria->getFields());
        static::assertSame([], $criteria->getExcludedFields());

        $criteria->excludeFields(['description']);
        $criteria->resetFields();

        static::assertSame([], $criteria->getFields());
        static::assertSame([], $criteria->getExcludedFields());
    }
}
