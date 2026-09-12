<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\NumberRange\ValueGenerator\IncrementSqlStorageTest
 */
class IncrementSqlStorage extends AbstractIncrementStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public function reserve(array $config, Context $context): int
    {
        $this->assertConfigurationMatchesContext($config, $context);

        $start = $config['start'] ?? 1;
        $varname = Uuid::randomHex();
        $stateId = Uuid::randomBytes();
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `data_scope_id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :dataScopeId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = @nr' . $varname . ' := IF(`last_value`+1 > :value, `last_value`+1, :value)',
            [
                'dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId()),
                'value' => $start,
                'id' => Uuid::fromHexToBytes($config['id']),
                'stateId' => $stateId,
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $result = $this->connection->executeQuery('SELECT @nr' . $varname);

        $lastNumber = $result->fetchOne();

        if (!$lastNumber) {
            return $start;
        }

        return (int) $lastNumber;
    }

    public function preview(array $config, Context $context): int
    {
        $this->assertConfigurationMatchesContext($config, $context);

        $result = $this->connection->executeQuery(
            'SELECT `last_value` FROM `number_range_state` WHERE number_range_id = :id AND data_scope_id = :dataScopeId',
            [
                'id' => Uuid::fromHexToBytes($config['id']),
                'dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId()),
            ]
        );
        $lastNumber = $result->fetchOne();

        $start = (int) ($config['start'] ?? 1);

        if ($lastNumber === false || (int) $lastNumber < $start) {
            $nextNumber = $start;
        } else {
            $nextNumber = $lastNumber + 1;
        }

        return $nextNumber;
    }

    public function list(Context $context): array
    {
        /** @var list<array{data_scope_id: string, number_range_id: string, last_value: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(`data_scope_id`)) AS `data_scope_id`,
                   LOWER(HEX(`number_range_id`)) AS `number_range_id`,
                   `last_value`
            FROM `number_range_state`
            WHERE `data_scope_id` = :dataScopeId
        ', [
            'dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId()),
        ]);

        $states = [];
        foreach ($rows as $row) {
            $states[$row['number_range_id']] = new IncrementState(
                $row['data_scope_id'],
                $row['number_range_id'],
                (int) $row['last_value'],
            );
        }

        return $states;
    }

    public function set(IncrementState $state, Context $context): void
    {
        $this->assertStateMatchesContext($state, $context);

        $stateId = Uuid::randomBytes();
        $this->connection->executeStatement(
            'INSERT `number_range_state` (`id`, `data_scope_id`, `last_value`, `number_range_id`, `created_at`) VALUES (:stateId, :dataScopeId, :value, :id, :createdAt)
                ON DUPLICATE KEY UPDATE
                `last_value` = :value',
            [
                'dataScopeId' => Uuid::fromHexToBytes($state->dataScopeId),
                'value' => $state->value,
                'id' => Uuid::fromHexToBytes($state->numberRangeId),
                'stateId' => $stateId,
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
