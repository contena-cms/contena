<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryDefinition;
use Contena\Core\System\Member\Aggregate\MemberTag\MemberTagDefinition;
use Contena\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\UserDefinition;

class MemberDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'member';

    final public const int MAX_LENGTH_NAME = 255;

    final public const int MAX_LENGTH_PHONE_NUMBER = 32;

    final public const int MAX_LENGTH_TITLE = 100;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MemberCollection::class;
    }

    public function getEntityClass(): string
    {
        return MemberEntity::class;
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function hasManyToManyIdFields(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the member.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member.'),
            new FkField('member_group_id', 'groupId', MemberGroupDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of member group.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of channel.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language.'),
            new AutoIncrementField(),
            new NumberRangeField('member_number', 'memberNumber', 255)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Unique number assigned to identify a member.'),
            new StringField('name', 'name', self::MAX_LENGTH_NAME)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Display name of the member.'),
            new StringField('phone_number', 'phoneNumber', self::MAX_LENGTH_PHONE_NUMBER)->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Phone number of the member.'),
            new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_MEMBER)->removeFlag(ApiAware::class),
            new EmailField('email', 'email')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false))->setDescription('Email address of the member.'),
            new StringField('title', 'title', self::MAX_LENGTH_TITLE)->addFlags(new ApiAware())->setDescription('Title or honorific of the member.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When true, the member account is active.'),
            new BoolField('double_opt_in_registration', 'doubleOptInRegistration')->addFlags(new ApiAware())->setDescription('When true, the member registration requires double opt-in.'),
            new DateTimeField('double_opt_in_email_sent_date', 'doubleOptInEmailSentDate')->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was sent.'),
            new DateTimeField('double_opt_in_confirm_date', 'doubleOptInConfirmDate')->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was confirmed.'),
            new StringField('hash', 'hash')->addFlags(new ApiAware())->setDescription('Registration double opt-in hash for confirming the member account.'),
            new DateTimeField('first_login', 'firstLogin')->addFlags(new ApiAware())->setDescription('Date and time of the member first login.'),
            new DateTimeField('last_login', 'lastLogin')->addFlags(new ApiAware())->setDescription('Date and time of the member last login.'),
            new DateField('birthday', 'birthday')->addFlags(new ApiAware())->setDescription('Birthday of the member.'),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields for the member.'),
            new ManyToOneAssociationField('group', 'member_group_id', MemberGroupDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Member group determining permissions and registration settings.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Preferred language for member communication.'),
            new OneToManyAssociationField('addresses', MemberAddressDefinition::class, 'member_id', 'id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('All addresses saved for the member.'),
            new ManyToManyAssociationField('tags', TagDefinition::class, MemberTagDefinition::class, 'member_id', 'tag_id')->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING), new ApiAware())->setDescription('Tags assigned to the member for organization and segmentation.'),
            new OneToOneAssociationField('recoveryMember', 'id', 'member_id', MemberRecoveryDefinition::class, false),
            new RemoteAddressField('remote_address', 'remoteAddress')->setDescription('Anonymous IP address of the member for the last session.'),
            new ManyToManyIdField('tag_ids', 'tagIds', 'tags')->addFlags(new ApiAware())->setDescription('Unique identities of tags.'),
            new FkField('requested_member_group_id', 'requestedGroupId', MemberGroupDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of requested member group.'),
            new ManyToOneAssociationField('requestedGroup', 'requested_member_group_id', MemberGroupDefinition::class, 'id', false),
            new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE])->addFlags(new ApiAware()),
            new UpdatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE])->addFlags(new ApiAware()),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('updatedBy', 'updated_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
