<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\ActionButton\AppAction;
use Contena\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Contena\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Contena\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Contena\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Contena\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(NotificationResponseFactory::class)]
class NotificationResponseFactoryTest extends TestCase
{
    private NotificationResponseFactory $factory;

    private AppAction $action;

    protected function setUp(): void
    {
        $this->factory = new NotificationResponseFactory();
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setAppSecret('app-secret');
        $this->action = new AppAction(
            $app,
            new Source('http://shop.url', 'shop-id', '1.0.0'),
            'http://target.url',
            'user',
            'action-name',
            [Uuid::randomHex(), Uuid::randomHex()],
            'action-it'
        );
    }

    #[DataProvider('provideActionTypes')]
    public function testSupportsOnlyNotificationActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesNotificationResponse(): void
    {
        $response = $this->factory->create($this->action, [], Context::createDefaultContext());

        static::assertInstanceOf(NotificationResponse::class, $response);
    }

    /**
     * @return array<int, array<int, string|bool>>
     */
    public static function provideActionTypes(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, true],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, false],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }
}
