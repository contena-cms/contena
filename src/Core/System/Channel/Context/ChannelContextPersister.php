<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Event\ChannelContextTokenChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Channel\Context\ChannelContextServiceTest
 */
class ChannelContextPersister
{
    private readonly string $lifetimeInterval;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
        ?string $lifetimeInterval = 'P1D',
    ) {
        $this->lifetimeInterval = $lifetimeInterval ?? 'P1D';
    }

    /**
     * @param array<string, mixed> $newParameters
     */
    public function save(string $token, array $newParameters, string $channelId, ?string $memberId = null): void
    {
        $existing = $this->load($token, $channelId, $memberId);

        $parameters = array_replace_recursive($existing, $newParameters);
        if (isset($newParameters['permissions']) && $newParameters['permissions'] === []) {
            $parameters['permissions'] = [];
        }

        unset($parameters['token']);

        $data = [
            'token' => $token,
            'payload' => Json::encode($parameters),
            'channelId' => Uuid::fromHexToBytes($channelId),
            'memberId' => $memberId !== null ? Uuid::fromHexToBytes($memberId) : null,
            'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->transactional(function () use ($token, $channelId, $data): void {
            if (!$this->tokenExistsForChannel($token, $channelId)) {
                $this->connection->insert('channel_api_context', [
                    'token' => $data['token'],
                    'payload' => $data['payload'],
                    'channel_id' => $data['channelId'],
                    'member_id' => $data['memberId'],
                    'updated_at' => $data['updatedAt'],
                ]);

                return;
            }

            $this->connection->executeStatement(
                'UPDATE channel_api_context
                 SET payload = :payload, member_id = :memberId, updated_at = :updatedAt
                 WHERE token = :token AND channel_id = :channelId',
                $data,
            );
        });
    }

    public function delete(string $token, string $channelId, ?string $memberId = null): void
    {
        $sql = 'DELETE FROM channel_api_context WHERE token = :token AND channel_id = :channelId';
        $parameters = [
            'token' => $token,
            'channelId' => Uuid::fromHexToBytes($channelId),
        ];
        if ($memberId !== null) {
            $sql .= ' AND member_id = :memberId';
            $parameters['memberId'] = Uuid::fromHexToBytes($memberId);
        }

        $this->connection->executeStatement(
            $sql,
            $parameters,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $token, string $channelId, ?string $memberId = null): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('*');
        $query->from('channel_api_context');

        $query->where('channel_id = :channelId');
        $query->setParameter('channelId', Uuid::fromHexToBytes($channelId));

        if ($memberId !== null) {
            $query->andWhere('(token = :token OR member_id = :memberId)');
            $query->setParameter('token', $token);
            $query->setParameter('memberId', Uuid::fromHexToBytes($memberId));
            $query->setMaxResults(2);
        } else {
            $query->andWhere('token = :token');
            $query->setParameter('token', $token);
            $query->setMaxResults(1);
        }

        $data = $query->executeQuery()->fetchAllAssociative();
        if ($data === []) {
            return [];
        }

        $memberContext = $memberId !== null ? $this->getMemberContext($data, $channelId, $memberId) : null;
        $context = $memberContext ?? array_shift($data);

        $updatedAt = new \DateTimeImmutable((string) $context['updated_at']);
        $expiredTime = $updatedAt->add(new \DateInterval($this->lifetimeInterval));

        $payload = array_filter(Json::decodeToArray((string) $context['payload']));
        if ($expiredTime < $this->clock->now()) {
            $payload = $memberId !== null ? [...$payload, 'expired' => true] : ['expired' => true];
        } else {
            $payload['expired'] = false;
        }

        $payload['token'] = (string) $context['token'];

        return $payload;
    }

    public function revokeAllMemberTokens(string $memberId, string ...$preserveTokens): void
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->update('channel_api_context')
            ->set('payload', ':payload')
            ->set('member_id', 'NULL')
            ->set('updated_at', ':updatedAt')
            ->where('member_id = :memberId')
            ->setParameter('updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->setParameter('payload', Json::encode([ChannelContextService::MEMBER_ID => null]))
            ->setParameter('memberId', Uuid::fromHexToBytes($memberId));

        if ($preserveTokens !== []) {
            $query
                ->andWhere($query->expr()->notIn('token', ':preserveTokens'))
                ->setParameter('preserveTokens', $preserveTokens, ArrayParameterType::STRING);
        }

        $query->executeStatement();
    }

    public function replace(string $oldToken, ChannelContext $context): string
    {
        $newToken = Random::getAlphanumericString(32);
        $channelId = $context->getChannelId();

        $this->connection->transactional(function () use ($oldToken, $newToken, $channelId, $context): void {
            if ($this->tokenExistsForChannel($oldToken, $channelId)) {
                $this->connection->executeStatement(
                    'UPDATE channel_api_context
                     SET token = :newToken, updated_at = :updatedAt
                     WHERE token = :oldToken AND channel_id = :channelId',
                    [
                        'newToken' => $newToken,
                        'oldToken' => $oldToken,
                        'channelId' => Uuid::fromHexToBytes($channelId),
                        'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                );

                return;
            }

            $this->connection->insert('channel_api_context', [
                'token' => $newToken,
                'payload' => Json::encode([]),
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'member_id' => $context->getMemberId() !== null ? Uuid::fromHexToBytes($context->getMemberId()) : null,
                'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        });

        $context->assign(['token' => $newToken]);

        $this->eventDispatcher->dispatch(new ChannelContextTokenChangeEvent($context, $oldToken, $newToken));

        return $newToken;
    }

    private function tokenExistsForChannel(string $token, string $channelId): bool
    {
        $storedChannelId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(channel_id)) FROM channel_api_context WHERE token = :token FOR UPDATE',
            ['token' => $token],
        );
        if ($storedChannelId === false) {
            return false;
        }

        if (!\is_string($storedChannelId) || $storedChannelId !== strtolower($channelId)) {
            throw ChannelException::contextTokenScopeMismatch();
        }

        return true;
    }

    /**
     * @param array<array<string, mixed>> $data
     *
     * @return array<string, mixed>|null
     */
    private function getMemberContext(array $data, string $channelId, string $memberId): ?array
    {
        foreach ($data as $row) {
            if (($row['member_id'] ?? null) !== null
                && Uuid::fromBytesToHex($row['channel_id']) === $channelId
                && Uuid::fromBytesToHex($row['member_id']) === $memberId
            ) {
                return $row;
            }
        }

        return null;
    }
}
