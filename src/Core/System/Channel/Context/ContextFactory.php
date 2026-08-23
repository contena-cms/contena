<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminChannelApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Event\ContextCreatedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type BaseContextOptions array{originalContext?: Context, version-id?: string, languageId?: string}
 *
 * @final
 */
class ContextFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param BaseContextOptions $options
     */
    public function getContext(string $channelId, array $options): Context
    {
        $sql = '
        # context-factory::base-context

        SELECT
          channel.id as channel_id,
          channel.language_id as channel_default_language_id,
          LOWER(HEX(channel.tenant_id)) as channel_tenant_id,
          GROUP_CONCAT(LOWER(HEX(channel_language.language_id))) as channel_language_ids
        FROM channel
            LEFT JOIN channel_language
                ON channel_language.channel_id = channel.id
        WHERE channel.id = :id
        GROUP BY channel.id, channel.language_id, channel.tenant_id';

        $data = $this->connection->fetchAssociative($sql, [
            'id' => Uuid::fromHexToBytes($channelId),
        ]);
        if ($data === false) {
            throw ChannelException::noContextData($channelId);
        }

        $originalContext = $options[ChannelContextService::ORIGINAL_CONTEXT] ?? null;
        if ($originalContext instanceof Context) {
            $origin = new AdminChannelApiSource($channelId, $originalContext);
        } else {
            $origin = new ChannelApiSource($channelId);
        }

        // explode all available languages for the provided channel
        $languageIds = $data['channel_language_ids'] ? explode(',', (string) $data['channel_language_ids']) : [];
        $languageIds = array_keys(array_flip($languageIds));

        // check which language should be used in the current request
        $defaultLanguageId = Uuid::fromBytesToHex($data['channel_default_language_id']);
        $languageChain = $this->buildLanguageChain($options, $defaultLanguageId, $languageIds);
        $versionId = $options[ChannelContextService::VERSION_ID] ?? $originalContext?->getVersionId() ?? Defaults::LIVE_VERSION;
        $considerInheritance = $originalContext?->considerInheritance() ?? true;
        $tenantId = $data['channel_tenant_id'] ?? null;

        // Channel contexts inherit the tenant of their channel.
        $context = new Context(
            $origin,
            $languageChain,
            $versionId,
            $considerInheritance,
            tenantId: $tenantId,
        );

        return $this->eventDispatcher->dispatch(new ContextCreatedEvent($context))->context;
    }

    /**
     * @param BaseContextOptions $sessionOptions
     * @param array<string> $availableLanguageIds
     *
     * @return non-empty-list<string>
     */
    private function buildLanguageChain(array $sessionOptions, string $defaultLanguageId, array $availableLanguageIds): array
    {
        $current = $sessionOptions[ChannelContextService::LANGUAGE_ID] ?? $defaultLanguageId;

        if (!\is_string($current) || !Uuid::isValid($current)) {
            throw ChannelException::invalidLanguageId();
        }

        if (!\in_array($current, $availableLanguageIds, true)) {
            throw ChannelException::providedLanguageNotAvailable($current, $availableLanguageIds);
        }

        if ($current === Defaults::LANGUAGE_SYSTEM) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        return array_values(array_filter([$current, $this->getParentLanguageId($current), Defaults::LANGUAGE_SYSTEM]));
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        $data = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.parent_id))')
            ->from('language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->executeQuery()
            ->fetchOne();

        if ($data === false) {
            throw ChannelException::languageNotFound($languageId);
        }

        return $data;
    }
}
