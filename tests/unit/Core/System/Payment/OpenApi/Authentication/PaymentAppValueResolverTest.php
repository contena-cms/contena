<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\OpenApi\Authentication;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\OpenApi\PaymentAppValueResolver;
use Contena\Core\System\Payment\OpenApi\Subscriber\PaymentAppValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(PaymentAppValueResolver::class)]
final class PaymentAppValueResolverTest extends TestCase
{
    public function testResolvesAuthenticatedPaymentAppArgument(): void
    {
        $app = new PaymentAppEntity();
        $request = new Request(attributes: [PaymentAppValidator::ATTRIBUTE_PAYMENT_APP => $app]);

        $resolved = iterator_to_array(new PaymentAppValueResolver()->resolve(
            $request,
            new ArgumentMetadata('app', PaymentAppEntity::class, false, false, null),
        ));

        static::assertSame([$app], $resolved);
    }

    public function testIgnoresOtherArguments(): void
    {
        $resolved = iterator_to_array(new PaymentAppValueResolver()->resolve(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new \stdClass()]),
            new ArgumentMetadata('value', 'string', false, false, null),
        ));

        static::assertSame([], $resolved);
    }

    public function testDoesNotResolveWhenAuthenticationDidNotSetAnApp(): void
    {
        $resolved = iterator_to_array(new PaymentAppValueResolver()->resolve(
            new Request(),
            new ArgumentMetadata('app', PaymentAppEntity::class, false, false, null),
        ));

        static::assertSame([], $resolved);
    }
}
