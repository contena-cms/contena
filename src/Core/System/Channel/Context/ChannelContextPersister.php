<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextTokenChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ChannelContextPersister
{
    private readonly string $lifetimeInterval;


    /**
     * @codeCoverageIgnore
     *
     * @see \Contena\Tests\Integration\Core\System\Channel\Context\ChannelContextPersisterTest
     *
     * @param array<string, mixed> $newParameters
     */
    public function save(string $token, array $newParameters, string $channelId, ?string $userId = null): void
    {
        $existing = $this->load($token, $channelId, $userId);

        $parameters = array_replace_recursive($existing, $newParameters);
        if (isset($newParameters['permissions']) && $newParameters['permissions'] === []) {
            $parameters['permissions'] = [];
        }

        unset($parameters['token']);

        $data = [
            'token' => $token,
            'payload' => json_encode($parameters, \JSON_THROW_ON_ERROR),
            'channelId' => $channelId ? Uuid::fromHexToBytes($channelId) : null,
            'userId' => $userId ? Uuid::fromHexToBytes($userId) : null,
            'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        // Update in place for the usual single-key conflict to avoid REPLACE's delete and insert lock pattern.
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(<<<'SQL'
                INSERT INTO channel_api_context (`token`, `payload`, `channel_id`, `user_id`, `updated_at`)
                VALUES (:token, :payload, :channelId, :userId, :updatedAt)
                ON DUPLICATE KEY UPDATE
                    `token` = :token,
                    `payload` = :payload,
                    `channel_id` = :channelId,
                    `user_id` = :userId,
                    `updated_at` = :updatedAt
                SQL)
        );

        try {
            $query->execute($data);
        } catch (UniqueConstraintViolationException) {
            // When both unique keys conflict, retain REPLACE's behavior of removing both conflicting rows.
            $query = new RetryableQuery(
                $this->connection,
                $this->connection->prepare(
                    'REPLACE INTO channel_api_context (`token`, `payload`, `channel_id`, `user_id`, `updated_at`)
                    VALUES (:token, :payload, :channelId, :userId, :updatedAt)'
                )
            );

            $query->execute($data);
        }
    }

    public function delete(string $token, string $channelId, ?string $userId = null): void
    {
        $this->connection->executeStatement(
            'DELETE FROM channel_api_context WHERE token = :token',
            [
                'token' => $token,
            ]
        );
    }

    public function replace(string $oldToken, ChannelContext $context): string
    {
        $newToken = Random::getAlphanumericString(32);

        $affected = $this->connection->executeStatement(
            'UPDATE `channel_api_context`
                   SET `token` = :newToken,
                       `updated_at` = :updatedAt
                   WHERE `token` = :oldToken',
            [
                'newToken' => $newToken,
                'oldToken' => $oldToken,
                'updatedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        if ($affected === 0) {
            $user = $context->getUser();

            $this->connection->insert('channel_api_context', [
                'token' => $newToken,
                'payload' => json_encode([]),
                'channel_id' => Uuid::fromHexToBytes($context->getChannelId()),
                'user_id' => $user ? Uuid::fromHexToBytes($user->getId()) : null,
                'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $this->cartPersister->replace($oldToken, $newToken, $context);

        $context->assign(['token' => $newToken]);
        $this->eventDispatcher->dispatch(new ChannelContextTokenChangeEvent($context, $oldToken, $newToken));

        return $newToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $token, string $channelId, ?string $userId = null): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*');
        $qb->from('channel_api_context');

        $qb->where('channel_id = :channelId');
        $qb->setParameter('channelId', Uuid::fromHexToBytes($channelId));

        if ($userId !== null) {
            $qb->andWhere('(token = :token OR user_id = :userId)');
            $qb->setParameter('token', $token);
            $qb->setParameter('userId', Uuid::fromHexToBytes($userId));
            $qb->setMaxResults(2);
        } else {
            $qb->andWhere('token = :token');
            $qb->setParameter('token', $token);
            $qb->setMaxResults(1);
        }

        $data = $qb->executeQuery()->fetchAllAssociative();

        if ($data === []) {
            return [];
        }

        $userContext = $channelId && $userId ? $this->getUserContext($data, $channelId, $userId) : null;

        $context = $userContext ?? array_shift($data);

        $updatedAt = new \DateTimeImmutable($context['updated_at']);
        $expiredTime = $updatedAt->add(new \DateInterval($this->lifetimeInterval));

        $payload = array_filter(json_decode((string) $context['payload'], true, 512, \JSON_THROW_ON_ERROR));
        $now = $this->clock->now();
        if ($expiredTime < $now) {
            // context is expired
            if ($userId !== null) {
                $payload['expired'] = true;
            } else {
                $payload = ['expired' => true];
            }
        } else {
            $payload['expired'] = false;
        }

        $payload['token'] = $context['token'];

        if (!isset($payload[ChannelContextService::CUSTOMER_ID])
            && \is_string($context['user_id'] ?? null)
            && $context['user_id'] !== ''
        ) {
            $payload[ChannelContextService::CUSTOMER_ID] = Uuid::fromBytesToHex($context['user_id']);
        }

        return $payload;
    }

    public function revokeAllUserTokens(string $userId, string ...$preserveTokens): void
    {
        $revokeParams = [
            'userId' => null,
            'billingAddressId' => null,
            'shippingAddressId' => null,
        ];

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->update('channel_api_context')
            ->set('payload', ':payload')
            ->set('user_id', 'NULL')
            ->set('updated_at', ':updatedAt')
            ->where('user_id = :userId')
            ->setParameter('updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->setParameter('payload', json_encode($revokeParams))
            ->setParameter('userId', Uuid::fromHexToBytes($userId));

        // keep tokens valid, which are given in $preserveTokens
        if ($preserveTokens) {
            $qb
                ->andWhere($qb->expr()->notIn('token', ':preserveTokens'))
                ->setParameter('preserveTokens', $preserveTokens, ArrayParameterType::STRING);
        }

        $qb->executeStatement();
    }

    /**
     * @param array<array<string, mixed>> $data
     *
     * @return array<string, mixed>|null
     */
    private function getUserContext(array $data, string $channelId, string $userId): ?array
    {
        foreach ($data as $row) {
            $userIdFromRow = $row['user_id'] ?? null;
            if (\is_string($userIdFromRow) && $userIdFromRow !== ''
&& Uuid::fromBytesToHex($row['channel_id']) === $channelId
&& Uuid::fromBytesToHex($userIdFromRow) === $userId
            ) {
                return $row;
            }
        }

        return null;
    }
}
