<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;

class VersionCommitDataDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'version_commit_data';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return false;
    }

    public function getCollectionClass(): string
    {
        return VersionCommitDataCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionCommitDataEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return VersionCommitDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new FkField('version_commit_id', 'versionCommitId', VersionCommitDefinition::class)->addFlags(new Required()),
            new ManyToOneAssociationField('commit', 'version_commit_id', VersionCommitDefinition::class, 'id', false),
            new IdField('user_id', 'userId'),
            new IdField('integration_id', 'integrationId'),
            new AutoIncrementField(),
            new StringField('entity_name', 'entityName')->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new JsonField('entity_id', 'entityId')->addFlags(new Required()),
            new StringField('action', 'action')->addFlags(new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING)),
            new VersionDataPayloadField('payload', 'payload')->addFlags(new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING)),
        ]);
    }
}
