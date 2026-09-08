<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DependencyInjectionException::class)]
class DependencyInjectionExceptionTest extends TestCase
{
    public function testProjectDirNotInContainer(): void
    {
        $this->expectExceptionObject(DependencyInjectionException::projectDirNotInContainer());

        throw DependencyInjectionException::projectDirNotInContainer();
    }

    public function testBundlesMetadataIsNotAnArray(): void
    {
        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
    }
}
