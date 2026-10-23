<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Flow\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionLoadedSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppFlowActionLoadedSubscriber::class)]
class AppFlowActionLoadedSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            'app_flow_action.loaded' => 'unserialize',
        ], AppFlowActionLoadedSubscriber::getSubscribedEvents());
    }

    public function testUnserialize(): void
    {
        $idFlowAction = Uuid::randomHex();

        $appFlowAction = new AppFlowActionEntity();
        $appFlowAction->setId($idFlowAction);
        $iconPath = __DIR__ . '/../../Manifest/_fixtures/icon.png';

        $fileIcon = '';
        if (\is_file($iconPath)) {
            $fileIcon = \file_get_contents($iconPath);
        }

        $appFlowAction->setIconRaw($fileIcon !== false ? $fileIcon : null);

        $subscriber = new AppFlowActionLoadedSubscriber();
        $event = new EntityLoadedEvent(new AppFlowActionDefinition(), [$appFlowAction], Context::createDefaultContext());

        $subscriber->unserialize($event);
        static::assertNotFalse($fileIcon);

        static::assertSame(
            base64_encode($fileIcon),
            $appFlowAction->getIcon()
        );
    }
}
