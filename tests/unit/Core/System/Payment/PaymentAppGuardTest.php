<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PaymentAppGuard::class)]
final class PaymentAppGuardTest extends TestCase
{
    #[DataProvider('scopeMatrix')]
    public function testApplicationRequiresItsConcreteOwnerScope(?string $owner, string $scope, bool $allowed): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'guard', 'status' => true, 'tenantId' => $owner]);
        $context = match ($scope) {
            'platform' => Context::createDefaultContext(),
            'global' => Context::createGlobalContext(),
            default => Context::createTenantContext($scope),
        };

        if (!$allowed) {
            $this->expectExceptionObject($owner !== $context->getTenantId()
                ? PaymentException::appNotFound($app->appCode)
                : PaymentException::invalidRequest('Payment operations require a platform or tenant context.'));
        }

        new PaymentAppGuard()->validate($app, $context);
        static::assertTrue($allowed);
    }

    public function testDisabledApplicationCannotExecute(): void
    {
        $app = new PaymentAppEntity()->assign(['id' => Uuid::randomHex(), 'appCode' => 'disabled', 'status' => false]);

        $this->expectExceptionObject(PaymentException::appNotFound('disabled'));
        new PaymentAppGuard()->validate($app, Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{?string, string, bool}>
     */
    public static function scopeMatrix(): iterable
    {
        $tenant = '11111111111111111111111111111111';
        $other = '22222222222222222222222222222222';
        yield 'platform app in platform scope' => [null, 'platform', true];
        yield 'platform app hidden from tenant' => [null, $tenant, false];
        yield 'platform app hidden from another tenant' => [null, $other, false];
        yield 'global must resolve a concrete scope' => [null, 'global', false];
        yield 'tenant app hidden from platform' => [$tenant, 'platform', false];
        yield 'tenant app in owning scope' => [$tenant, $tenant, true];
        yield 'tenant app hidden from another tenant' => [$tenant, $other, false];
        yield 'tenant app cannot execute globally' => [$tenant, 'global', false];
    }
}
