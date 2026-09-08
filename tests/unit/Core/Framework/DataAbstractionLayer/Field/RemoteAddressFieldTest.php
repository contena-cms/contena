<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RemoteAddressField::class)]
class RemoteAddressFieldTest extends TestCase
{
    public function testGetStorageName(): void
    {
        $field = new RemoteAddressField('remote_address', 'remoteAddress');

        static::assertSame('remote_address', $field->getStorageName());
    }
}
