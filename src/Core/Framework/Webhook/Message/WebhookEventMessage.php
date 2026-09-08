<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Message;

/**
 * @internal
 */
class WebhookEventMessage
{
    public const DEFAULT_PARTITION_KEY = 'default';

    /**
     * @internal
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
     **/
    public function __construct(
        private readonly string $webhookEventId,
        private readonly array $payload,
        private readonly ?string $appId,
        private readonly string $webhookId,
        private readonly string $contenaVersion,
        private readonly string $url,
        private readonly ?string $secret,
        private readonly string $languageId,
        private readonly string $userLocale,
        private readonly array $webhookHeaders,
        public readonly string $partitionKey,
        /**
         * The app's name. Lets delivery look up the signing secret from deleted_apps after an app is
         * uninstalled (for example the app.deleted webhook). Null for non-app webhooks, and for
         * messages that were already queued before this field was added.
         */
        private readonly ?string $appName = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function getAppName(): ?string
    {
        return $this->appName;
    }

    public function getWebhookId(): string
    {
        return $this->webhookId;
    }

    public function getContenaVersion(): string
    {
        return $this->contenaVersion;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getWebhookEventId(): string
    {
        return $this->webhookEventId;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function getUserLocale(): ?string
    {
        return $this->userLocale;
    }

    /**
     * @return array<string, string>
     */
    public function getWebhookHeaders(): array
    {
        return $this->webhookHeaders;
    }

    /**
     * Returns the raw partition key input (e.g. app ID or 'default').
     */
    public function getPartitionKey(): string
    {
        return $this->partitionKey;
    }
}
