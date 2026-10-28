<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\UsageData\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\Event\ConsentAcceptedEvent;
use Contena\Core\System\Consent\Event\ConsentRevokedEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\UsageData\Services\EntityDispatchService;
use Contena\Core\System\UsageData\Subscriber\ConsentStateChangedSubscriber;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentStateChangedSubscriber::class)]
class ConsentStateChangedSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ConsentAcceptedEvent::class => 'handleConsentAcceptedEvent',
                ConsentRevokedEvent::class => 'handleConsentRevokedEvent',
            ],
            ConsentStateChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testAcceptConsentHandlesOnlyBackendData(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())->method('dispatchCollectEntityDataMessage');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())->method('set')->with('core.usageData.consentState', 'accepted');

        $subscriber = new ConsentStateChangedSubscriber($entityDispatchService, $systemConfigService);

        $subscriber->handleConsentAcceptedEvent(new ConsentAcceptedEvent(
            BackendData::NAME,
            'system',
            'system',
            'actor'
        ));
    }

    public function testAcceptConsentIgnoresOtherConsentNames(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('set');

        $subscriber = new ConsentStateChangedSubscriber($entityDispatchService, $systemConfigService);

        $subscriber->handleConsentAcceptedEvent(new ConsentAcceptedEvent(
            'other-consent',
            'system',
            'system',
            'actor'
        ));
    }

    public function testRevokeConsentHandlesOnlyBackendData(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())->method('set')->with('core.usageData.consentState', 'revoked');

        $subscriber = new ConsentStateChangedSubscriber($entityDispatchService, $systemConfigService);

        $subscriber->handleConsentRevokedEvent(new ConsentRevokedEvent(
            BackendData::NAME,
            'system',
            'system',
            'actor'
        ));
    }

    public function testRevokeConsentIgnoresOtherConsentNames(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('set');

        $subscriber = new ConsentStateChangedSubscriber($entityDispatchService, $systemConfigService);

        $subscriber->handleConsentRevokedEvent(new ConsentRevokedEvent(
            'other-consent',
            'system',
            'system',
            'actor'
        ));
    }
}
