<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroup;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroupRegistrationChannel\MemberGroupRegistrationChannelDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationDefinition;
use Contena\Core\System\Member\MemberDefinition;

class MemberGroupDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'member_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MemberGroupCollection::class;
    }

    public function getEntityClass(): string
    {
        return MemberGroupEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member group.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of member group.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new BoolField('registration_active', 'registrationActive')->addFlags(new ApiAware())->setDescription('Enables public registration for this member group.'),
            new TranslatedField('registrationTitle')->addFlags(new ApiAware()),
            new TranslatedField('registrationIntroduction')->addFlags(new ApiAware()),
            new TranslatedField('registrationSeoMetaDescription')->addFlags(new ApiAware()),
            new TranslationsAssociationField(MemberGroupTranslationDefinition::class, 'member_group_id')->addFlags(new Required()),
            new OneToManyAssociationField('members', MemberDefinition::class, 'member_group_id', 'id')->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('channels', ChannelDefinition::class, 'member_group_id', 'id')->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('registrationChannels', ChannelDefinition::class, MemberGroupRegistrationChannelDefinition::class, 'member_group_id', 'channel_id'),
        ]);
    }
}
