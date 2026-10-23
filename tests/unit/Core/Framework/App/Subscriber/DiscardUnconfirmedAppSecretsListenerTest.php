<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Contena\Core\Framework\App\Subscriber\DiscardUnconfirmedAppSecretsListener;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(DiscardUnconfirmedAppSecretsListener::class)]
class DiscardUnconfirmedAppSecretsListenerTest extends TestCase
{
    public function testDiscardsUnconfirmedSecretsOfEveryPendingApp(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([['app-one', 'app-two']]);

        $listener = new DiscardUnconfirmedAppSecretsListener($appRepository);
        $listener(new ShopIdDeletedEvent());

        static::assertSame(
            [[
                ['id' => 'app-one', 'unconfirmedAppSecrets' => null],
                ['id' => 'app-two', 'unconfirmedAppSecrets' => null],
            ]],
            $appRepository->updates
        );
    }
}
