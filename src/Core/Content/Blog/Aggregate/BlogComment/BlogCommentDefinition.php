<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogComment;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Member\MemberDefinition;

/**
 * @codeCoverageIgnore
 */
class BlogCommentDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_comment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogCommentCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogCommentEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => false];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogCommentHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new DataScopeField()->setDescription('Non-null identity of the owning data scope.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the blog comment.'),
            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the blog.'),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('member_id', 'memberId', MemberDefinition::class)->setDescription('Unique identity of the member who authored the comment.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the channel.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the language.'),
            new ParentFkField(self::class)->addFlags(new ApiAware())->setDescription('Unique identity of the root comment this reply belongs to.'),
            new StringField('external_user', 'externalUser')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Display name used when no member is linked.'),
            new StringField('external_email', 'externalEmail')->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Email address used when no member is linked.'),
            new LongTextField('content', 'content')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('Content of the comment or reply.'),
            new BoolField('status', 'status')->addFlags(new ApiAware())->setDescription('When true, the comment is publicly visible.'),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false)->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('member', 'member_id', MemberDefinition::class, 'id', false)->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware()),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware(), new CascadeDelete()),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields for the blog comment.'),
        ]);
    }
}
