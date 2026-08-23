<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\Clock\Clock;

class ScheduledTaskDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'scheduled_task';

    final public const string STATUS_SCHEDULED = 'scheduled';

    final public const string STATUS_QUEUED = 'queued';

    final public const string STATUS_SKIPPED = 'skipped';

    final public const string STATUS_RUNNING = 'running';

    final public const string STATUS_FAILED = 'failed';

    final public const string STATUS_INACTIVE = 'inactive';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ScheduledTaskCollection::class;
    }

    public function getEntityClass(): string
    {
        return ScheduledTaskEntity::class;
    }

    public function getDefaults(): array
    {
        return ['nextExecutionTime' => Clock::get()->now()];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of scheduled task.'),
            new StringField('name', 'name')->addFlags(new Required())->setDescription('Name of the scheduled task.'),
            new StringField('scheduled_task_class', 'scheduledTaskClass', 512)->addFlags(new Required())->setDescription('Unique identity of scheduled task.'),
            new IntField('run_interval', 'runInterval', 0)->addFlags(new Required())->setDescription('The frequency interval at which the scheduled task must run like 5 min, 1 hours , etc'),
            new IntField('default_run_interval', 'defaultRunInterval', 0)->addFlags(new Required())->setDescription('Default run interval setting.'),
            new StringField('status', 'status')->addFlags(new Required())->setDescription('When status is set, the ScheduledTask is made visible.'),
            new DateTimeField('last_execution_time', 'lastExecutionTime')->setDescription('Time when the scheduled task was last executed.'),
            new DateTimeField('next_execution_time', 'nextExecutionTime')->addFlags(new Required())->setDescription('Time when the scheduled task will execute next.'),
        ]);
    }
}
