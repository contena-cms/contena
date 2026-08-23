<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log\Monolog;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;

class DoctrineSQLHandler extends AbstractProcessingHandler
{
    /**
     * @internal
     */
    public function __construct(
        protected Connection $connection,
        private readonly ClockInterface $clock,
        Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $envelope = [
            'id' => Uuid::randomBytes(),
            'tenant_id' => $this->getTenantId($record),
            'message' => $record->message,
            'level' => $record->level->value,
            'channel' => $record->channel,
            'context' => json_encode($record->context, \JSON_THROW_ON_ERROR),
            'extra' => json_encode($record->extra, \JSON_THROW_ON_ERROR),
            'updated_at' => null,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        try {
            $this->connection->insert('log_entry', $envelope);
        } catch (\Throwable) {
            $envelope['context'] = json_encode([]);
            $envelope['extra'] = json_encode([]);
            $this->connection->insert('log_entry', $envelope);
        }
    }

    private function getTenantId(LogRecord $record): ?string
    {
        $tenantId = $record->context['tenantId'] ?? null;

        return \is_string($tenantId) && Uuid::isValid($tenantId) ? Uuid::fromHexToBytes($tenantId) : null;
    }
}
