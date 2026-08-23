<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogVisibility;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class BlogVisibilityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_visibility';

    final public const VISIBILITY_LINK = 10;

    final public const VISIBILITY_SEARCH = 20;

    final public const VISIBILITY_ALL = 30;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogVisibilityEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogVisibilityCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogVisibilityHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned blog visibility.'),
            new IdField('id', 'id')->addFlags(new Required(), new PrimaryKey())->setDescription('Unique identity of blog visibility.'),

            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new Required())->setDescription('Unique identity of the blog.'),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new Required()),

            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new Required())->setDescription('Unique identity of the channel.'),
            new IntField('visibility', 'visibility')->addFlags(new Required(), new Choice([self::VISIBILITY_LINK, self::VISIBILITY_SEARCH, self::VISIBILITY_ALL], strict: true))->setDescription('An integer value to signify the blog\'s visibility in any channel. `10` indicates `Hide in listings and search`, `20` indicates `Hide in listings` and `30` indicates `Visible` everywhere.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
        ]);
    }
}
