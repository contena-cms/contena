<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Member\Service;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Maintenance\Member\Service\MemberProvisionerTest
 */
class MemberProvisioner
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
    ) {
    }

    public function provision(string $email, ?string $password = null, ?string $name = null): string
    {
        $channel = $this->getDefaultWebChannel();
        $password ??= Random::getAlphanumericString(8);

        $this->connection->insert('member', [
            'id' => Uuid::randomBytes(),
            'member_group_id' => $channel['member_group_id'],
            'channel_id' => $channel['id'],
            'language_id' => $channel['language_id'],
            'member_number' => $this->numberRangeValueGenerator->getValue(MemberDefinition::ENTITY_NAME, Context::createDefaultContext()),
            'name' => $name ?? $email,
            'password' => password_hash($password, \PASSWORD_DEFAULT),
            'email' => $email,
            'active' => true,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $password;
    }

    /**
     * @return array{id: string, member_group_id: string, language_id: string}
     */
    private function getDefaultWebChannel(): array
    {
        $channel = $this->connection->fetchAssociative(
            'SELECT `id`, `member_group_id`, `language_id`
             FROM `channel`
             WHERE `type_id` = :typeId AND `tenant_id` IS NULL
             ORDER BY `created_at`
             LIMIT 1',
            ['typeId' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_WEB)],
        );

        if (!\is_array($channel)
            || !\is_string($channel['id'] ?? null)
            || !\is_string($channel['member_group_id'] ?? null)
            || !\is_string($channel['language_id'] ?? null)
        ) {
            throw MaintenanceException::couldNotGetId('default web channel');
        }

        return $channel;
    }
}
