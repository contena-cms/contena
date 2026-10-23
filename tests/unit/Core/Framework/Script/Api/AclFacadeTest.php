<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Api\AclFacade;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AclFacade::class)]
class AclFacadeTest extends TestCase
{
    public function testCan(): void
    {
        $source = new AdminApiSource(null, Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions(['blog:read']);

        $context = Context::createCLIContext($source);

        $facade = new AclFacade($context);

        static::assertTrue($facade->can('blog:read'));
        static::assertFalse($facade->can('order:read'));
    }
}
