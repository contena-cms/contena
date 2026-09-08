<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Flow\Action\Action;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Contena\Core\Framework\Webhook\WebhookCacheClearer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type WebhookFromXml array{name: string, eventName: string, url: string, appId: string, onlyLiveVersion?: bool}
 * @phpstan-type WebhookRecord array{name: string, event_name: string, url: string, only_live_version: int, app_id: string}
 */
class WebhookLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WebhookCacheClearer $cacheClearer,
        private readonly ClockInterface $clock,
        private readonly WebhookTargetValidator $targetValidator,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        $appId = $context->app->getId();
        $appName = $context->manifest->getMetadata()->getName();
        $flowActions = $this->getFlowActions($context->appFilesystem);
        $webhooks = $this->getWebhooks($context->manifest, $flowActions, $appId, $context->defaultLocale, $context->hasAppSecret());

        $existingWebhooks = $this->getExistingWebhooks($appId);
        $updates = [];
        $inserts = [];

        foreach ($webhooks as $webhook) {
            $this->validateWebhookTarget($webhook['url'], $appName);
            $payload = $this->toRecord($webhook, $appId);

            if ($id = array_search($webhook['name'], $existingWebhooks, true)) {
                unset($existingWebhooks[$id]);
                $updates[$id] = $payload;
                continue;
            }

            $payload['id'] = Uuid::randomBytes();
            $payload['created_at'] = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $inserts[] = $payload;
        }

        foreach ($updates as $id => $update) {
            $this->connection->update('webhook', $update, ['id' => Uuid::fromHexToBytes($id)]);
        }

        foreach ($inserts as $insert) {
            $this->connection->insert('webhook', $insert);
        }

        $this->deleteOldWebhooks($existingWebhooks);
        $this->cacheClearer->clearWebhookCache();
    }

    /**
     * @param array<string, string> $toBeRemoved
     */
    private function deleteOldWebhooks(array $toBeRemoved): void
    {
        $this->connection->executeStatement(
            'DELETE FROM webhook WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_keys($toBeRemoved))],
            ['ids' => ArrayParameterType::STRING],
        );
    }

    /**
     * @return array<string, string>
     */
    private function getExistingWebhooks(string $appId): array
    {
        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(w.id)) as webhookId,
                w.name as webhookName
            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE LOWER(HEX(a.id)) = :appId

        SQL;

        /** @var array<string, string> $webhooks */
        $webhooks = $this->connection->fetchAllKeyValue(
            $sql,
            ['appId' => $appId]
        );

        return $webhooks;
    }

    /**
     * @param WebhookFromXml $webhook
     *
     * @return WebhookRecord
     */
    private function toRecord(array $webhook, string $appId): array
    {
        return [
            'name' => $webhook['name'],
            'event_name' => $webhook['eventName'],
            'url' => $webhook['url'],
            'only_live_version' => \array_key_exists('onlyLiveVersion', $webhook) ? (int) $webhook['onlyLiveVersion'] : 0,
            'app_id' => Uuid::fromHexToBytes($appId),
        ];
    }

    private function getFlowActions(Filesystem $fs): ?Action
    {
        if (!$fs->has('Resources/flow.xml')) {
            return null;
        }

        return Action::createFromXmlFile($fs->path('Resources/flow.xml'));
    }

    private function validateWebhookTarget(string $url, string $appName): void
    {
        if ($this->targetValidator->validate($url) === null) {
            throw AppException::registrationFailed($appName, 'Webhook target is not allowed.');
        }
    }

    /**
     * @return list<WebhookFromXml>
     */
    private function getWebhooks(Manifest $manifest, ?Action $flowActions, string $appId, string $defaultLocale, bool $hasAppSecret): array
    {
        $actions = [];

        if ($flowActions) {
            $actions = $flowActions->getActions()?->getActions() ?? [];
        }

        $webhooks = array_map(static function ($action) use ($appId) {
            $name = $action->getMeta()->getName();

            return [
                'name' => $name,
                'eventName' => $name,
                'url' => $action->getMeta()->getUrl(),
                'appId' => $appId,
            ];
        }, $actions);

        if (!$hasAppSecret) {
            return $webhooks;
        }

        $manifestWebhooks = $manifest->getWebhooks()?->getWebhooks() ?? [];

        return array_merge($webhooks, array_map(static function (Webhook $webhook) use ($defaultLocale, $appId) {
            $payload = $webhook->toArray($defaultLocale);
            unset($payload['event']);
            $payload['appId'] = $appId;
            $payload['eventName'] = $webhook->getEvent();

            return $payload;
        }, $manifestWebhooks));
    }
}
