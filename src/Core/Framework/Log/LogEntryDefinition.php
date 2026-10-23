<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class LogEntryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'log_entry';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LogEntryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LogEntryCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of log entry.'),

            (new IntField('level', 'level'))->setDescription('It indicates the level or severity of the log entry. For example: BUG, ERROR, etc.'),
            new StringField('channel', 'channel'),
        ]);
    }
}
