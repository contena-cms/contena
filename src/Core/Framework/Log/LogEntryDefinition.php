<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class LogEntryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'log_entry';

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
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of log entry.'),

            new LongTextField('message', 'message')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Indicates text or content of a log entry.'),
            new IntField('level', 'level')->setDescription('It indicates the level or severity of the log entry. For example: BUG, ERROR, etc.'),
            new StringField('channel', 'channel'),
            new JsonField('context', 'context')->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Information associated with a log entry.'),
            new JsonField('extra', 'extra')->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('Additional information associated with a log entry.'),
        ]);
    }
}
