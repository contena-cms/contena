<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserGroup;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\User\Aggregate\UserGroupRegistrationChannel\UserGroupRegistrationChannelDefinition;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
class UserGroupDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_group';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserGroupCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserGroupEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the user\'s group.'),
            (new BoolField('display_gross', 'displayGross'))->addFlags(new ApiAware())->setDescription('If boolean value is `true` gross value is displayed else, net value will be displayed to the user.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            // Merchant Registration
            (new BoolField('registration_active', 'registrationActive'))->addFlags(new ApiAware())->setDescription('To enable the registration of partner user group.'),
            (new TranslatedField('registrationTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationIntroduction'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationOnlyCompanyRegistration'))->addFlags(new ApiAware()),
            (new TranslatedField('registrationSeoMetaDescription'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('users', UserDefinition::class, 'user_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('channels', ChannelDefinition::class, 'user_group_id', 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(UserGroupTranslationDefinition::class, 'user_group_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('registrationChannels', ChannelDefinition::class, UserGroupRegistrationChannelDefinition::class, 'user_group_id', 'channel_id'),
        ]);
    }
}
