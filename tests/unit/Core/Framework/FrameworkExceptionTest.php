<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\FrameworkException;

/**
 * @internal
 */
#[CoversClass(FrameworkException::class)]
class FrameworkExceptionTest extends TestCase
{
    public function testProjectDirNotExists(): void
    {
        $this->expectExceptionObject(FrameworkException::projectDirNotExists('test'));

        throw FrameworkException::projectDirNotExists('test');
    }

    public function testCollectionElementInvalidType(): void
    {
        $this->expectExceptionObject(FrameworkException::collectionElementInvalidType('foo', 'bar'));

        throw FrameworkException::collectionElementInvalidType('foo', 'bar');
    }
}
