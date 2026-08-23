<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\ConsentScope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope\AdminUser;

/**
 * @internal
 */
#[CoversClass(AdminUser::class)]
class AdminUserTest extends TestCase
{
    public function testScope(): void
    {
        $scope = new AdminUser();

        static::assertSame('admin_user', $scope->getName());
    }

    public function testScopeIdentifierThrowsExceptionWhenSourceIsNotAdminApi(): void
    {
        self::expectExceptionObject(ConsentException::cannotResolveScope(AdminUser::NAME));

        $scope = new AdminUser();
        $scope->resolveIdentifier(Context::createDefaultContext());
    }

    public function testScopeIdentifierThrowsExceptionWhenUserIdIsNull(): void
    {
        self::expectExceptionObject(ConsentException::cannotResolveScope(AdminUser::NAME));

        $source = new AdminApiSource(null);
        $context = new Context($source);

        $scope = new AdminUser();
        $scope->resolveIdentifier($context);
    }

    public function testScopeIdentifier(): void
    {
        $source = new AdminApiSource('user-123');
        $context = new Context($source);

        $scope = new AdminUser();
        static::assertSame('user-123', $scope->resolveIdentifier($context));
    }

    public function testActorIdentifier(): void
    {
        $source = new AdminApiSource('user-123');
        $context = new Context($source);

        $scope = new AdminUser();
        static::assertSame('user-123', $scope->resolveActorIdentifier($context));
    }
}
