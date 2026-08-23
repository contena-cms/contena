<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Framework\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Framework\Search\CriteriaCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Notification\NotificationEntity;

/**
 * @internal
 */
#[CoversClass(CriteriaCollection::class)]
class CriteriaCollectionTest extends TestCase
{
    public function testGetExpectedClass(): void
    {
        $collection = new CriteriaCollection();

        $collection->add(new Criteria());

        $this->expectExceptionObject(FrameworkException::collectionElementInvalidType(Criteria::class, NotificationEntity::class));
        /** @phpstan-ignore argument.type (for test purpose) */
        $collection->add(new NotificationEntity());

        static::assertCount(1, $collection);
    }
}
