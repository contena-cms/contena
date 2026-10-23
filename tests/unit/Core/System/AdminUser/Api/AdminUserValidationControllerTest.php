<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Contena\Core\System\AdminUser\AdminUserException;
use Contena\Core\System\AdminUser\Api\AdminUserValidationController;
use Contena\Core\System\AdminUser\Service\AdminUserValidationService;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AdminUserValidationController::class)]
class AdminUserValidationControllerTest extends TestCase
{
    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testValidationRoutesRequireUserReadPrivilege(string $routeName, array $expectedPrivileges): void
    {
        $route = (new AttributeRouteControllerLoader())->load(AdminUserValidationController::class)->get($routeName);

        static::assertNotNull($route);
        static::assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('missingEmailParameterProvider')]
    public function testEmailValidationRejectsMissingParameters(array $payload, string $missingParameter): void
    {
        $controller = new AdminUserValidationController(static::createStub(AdminUserValidationService::class));

        static::expectExceptionObject(AdminUserException::missingRequestParameter($missingParameter));

        $controller->isEmailUnique(
            Request::create('/', Request::METHOD_POST, $payload),
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('missingUsernameParameterProvider')]
    public function testUsernameValidationRejectsMissingParameters(array $payload, string $missingParameter): void
    {
        $controller = new AdminUserValidationController(static::createStub(AdminUserValidationService::class));

        static::expectExceptionObject(AdminUserException::missingRequestParameter($missingParameter));

        $controller->isUsernameUnique(
            Request::create('/', Request::METHOD_POST, $payload),
            Context::createDefaultContext(),
        );
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'email uniqueness validation' => ['api.action.check-email-unique', ['admin_user:read']];
        yield 'username uniqueness validation' => ['api.action.check-username-unique', ['admin_user:read']];
    }

    /**
     * @return \Generator<string, array{0: array<string, string>, 1: string}>
     */
    public static function missingEmailParameterProvider(): \Generator
    {
        yield 'email is missing' => [['id' => 'id'], 'email'];
        yield 'email id is missing' => [['email' => 'email'], 'id'];
    }

    /**
     * @return \Generator<string, array{0: array<string, string>, 1: string}>
     */
    public static function missingUsernameParameterProvider(): \Generator
    {
        yield 'username is missing' => [['id' => 'id'], 'username'];
        yield 'username id is missing' => [['username' => 'username'], 'id'];
    }
}
