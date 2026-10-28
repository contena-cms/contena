<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Content\Media\Event\MediaUploadedEvent;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Event\AppActivatedEvent;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\Event\AppPermissionsUpdated;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\App\Event\SystemHeartbeatEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedHook;

#[Package('framework')]
interface Hookable
{
    public const NO_APP_ID = '01a02437bd6e72158b8efe5518ec349d';

    public const HOOKABLE_EVENTS = [
        MediaUploadedEvent::class => MediaUploadedEvent::EVENT_NAME,
        AppActivatedEvent::class => AppActivatedEvent::NAME,
        AppDeactivatedEvent::class => AppDeactivatedEvent::NAME,
        AppDeletedEvent::class => AppDeletedEvent::NAME,
        AppInstalledEvent::class => AppInstalledEvent::NAME,
        AppUpdatedEvent::class => AppUpdatedEvent::NAME,
        AppPermissionsUpdated::class => AppPermissionsUpdated::NAME,
        UpdatePostFinishEvent::class => UpdatePostFinishEvent::EVENT_NAME,
        SystemConfigChangedHook::class => SystemConfigChangedHook::EVENT_NAME,
        SystemHeartbeatEvent::class => SystemHeartbeatEvent::NAME,
    ];

    public const HOOKABLE_EVENTS_DESCRIPTION = [
        MediaUploadedEvent::class => 'Fires when a media file is uploaded',
        AppActivatedEvent::class => 'Fires when an app is activated',
        AppDeactivatedEvent::class => 'Fires when an app is deactivated',
        AppDeletedEvent::class => 'Fires when an app is deleted',
        AppInstalledEvent::class => 'Fires when an app is installed',
        AppUpdatedEvent::class => 'Fires when an app is updated',
        AppPermissionsUpdated::class => 'Fires when an apps permissions were updated with a list of the currently accepted permissions, eg after new were accepted or revoked',
        UpdatePostFinishEvent::class => 'Fires after an contena update has been finished',
        SystemConfigChangedHook::class => 'Fires when a system config value is changed',
        SystemHeartbeatEvent::class => 'Fires as a recurrent task. Indicates to the app that the system is up and running.',
    ];

    public const HOOKABLE_EVENTS_PRIVILEGES = [
        MediaUploadedEvent::class => ['media:read'],
        AppActivatedEvent::class => [],
        AppDeactivatedEvent::class => [],
        AppDeletedEvent::class => [],
        AppInstalledEvent::class => [],
        AppUpdatedEvent::class => [],
        AppPermissionsUpdated::class => [],
        UpdatePostFinishEvent::class => [],
        SystemConfigChangedHook::class => ['system_config:read'],
        SystemHeartbeatEvent::class => [],
    ];

    public function getName(): string;

    /**
     * @return array<mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array;

    /**
     * returns if it is allowed to dispatch the event to given app with given permissions
     */
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool;
}
