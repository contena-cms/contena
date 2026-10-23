<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;

/**
 * @internal
 */
#[CoversClass(Subscriber::class)]
class SubscriberTest extends TestCase
{
    public function testAnAppSubscriberCarriesItsManifest(): void
    {
        $manifest = static::createStub(Manifest::class);

        $subscriber = Subscriber::app($manifest);

        static::assertSame(SubscriberType::App, $subscriber->type);
        static::assertSame($manifest, $subscriber->manifest);
    }

    #[DataProvider('subscribersWithoutAManifest')]
    public function testASubscriberWithoutAManifestCarriesItsType(Subscriber $subscriber, SubscriberType $expectedType): void
    {
        static::assertSame($expectedType, $subscriber->type);
        static::assertNull($subscriber->manifest);
    }

    /**
     * @return iterable<string, array{Subscriber, SubscriberType}>
     */
    public static function subscribersWithoutAManifest(): iterable
    {
        yield 'a admin_user through the API' => [Subscriber::api(ApiCallerType::AdminUser), SubscriberType::AdminUser];
        yield 'an integration through the API' => [Subscriber::api(ApiCallerType::Integration), SubscriberType::Integration];
        yield 'no subscriber at all' => [Subscriber::none(), SubscriberType::None];
    }
}
