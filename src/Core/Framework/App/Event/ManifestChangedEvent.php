<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
abstract class ManifestChangedEvent extends AppChangedEvent
{
    public const LIFECYCLE_EVENTS = [
        AppActivatedEvent::NAME,
        AppDeactivatedEvent::NAME,
        AppDeletedEvent::NAME,
        AppInstalledEvent::NAME,
        AppUpdatedEvent::NAME,
    ];

    public function __construct(
        AppEntity $app,
        private readonly Manifest $manifest,
        Context $context
    ) {
        parent::__construct($app, $context);
    }

    abstract public function getName(): string;

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'appVersion' => $this->manifest->getMetadata()->getVersion(),
        ];
    }
}
