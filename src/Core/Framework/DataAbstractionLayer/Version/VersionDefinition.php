<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Symfony\Component\Clock\Clock;

class VersionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'version';

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
        return VersionCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionEntity::class;
    }

    public function getDefaults(): array
    {
        $dateTime = Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        return ['name' => \sprintf('Draft %s', $dateTime), 'createdAt' => $dateTime];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name')->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new OneToManyAssociationField('commits', VersionCommitDefinition::class, 'version_id'),
        ]);
    }
}
