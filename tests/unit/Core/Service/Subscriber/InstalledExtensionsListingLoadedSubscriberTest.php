<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Event\InstalledExtensionsListingLoadedEvent;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Service\Subscriber\InstalledExtensionsListingLoadedSubscriber;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(InstalledExtensionsListingLoadedSubscriber::class)]
class InstalledExtensionsListingLoadedSubscriberTest extends TestCase
{
    public function testExtensionsWithSameNameAsServicesAreRemoved(): void
    {
        $event = new InstalledExtensionsListingLoadedEvent(
            new ExtensionCollection([
                'Ext1' => ExtensionStruct::fromArray(['name' => 'Ext1', 'label' => 'Ext1', 'type' => 'type']),
                'Ext2' => ExtensionStruct::fromArray(['name' => 'Ext2', 'label' => 'Ext2', 'type' => 'type']),
            ]),
            Context::createDefaultContext()
        );

        $app1 = new AppEntity();
        $app1->setUniqueIdentifier(Uuid::randomHex());
        $app1->setName('Ext2');

        $appRepository = new StaticEntityRepository([
            new AppCollection([$app1]),
        ]);
        $subscriber = new InstalledExtensionsListingLoadedSubscriber($appRepository);

        $subscriber->removeAppsWithService($event);

        static::assertCount(1, $event->extensionCollection);
        $ext = $event->extensionCollection->first();
        static::assertNotNull($ext);
        static::assertSame('Ext1', $ext->getName());
    }
}
