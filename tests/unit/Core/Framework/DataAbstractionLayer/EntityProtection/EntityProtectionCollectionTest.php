<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(EntityProtectionCollection::class)]
class EntityProtectionCollectionTest extends TestCase
{
    public function testAddKeysTheElementByItsClass(): void
    {
        $collection = new EntityProtectionCollection();
        $protection = new ReadProtection();

        $collection->add($protection);

        static::assertSame($protection, $collection->get(ReadProtection::class));
    }

    public function testSetIgnoresTheGivenKeyInFavorOfTheClass(): void
    {
        $collection = new EntityProtectionCollection();
        $protection = new WriteProtection();

        $collection->set('custom-key', $protection);

        static::assertNull($collection->get('custom-key'));
        static::assertSame($protection, $collection->get(WriteProtection::class));
    }

    public function testApiAlias(): void
    {
        static::assertSame('dal_protection_collection', (new EntityProtectionCollection())->getApiAlias());
    }
}
