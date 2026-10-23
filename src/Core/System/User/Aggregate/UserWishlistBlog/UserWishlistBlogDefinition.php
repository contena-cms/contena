<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlistBlog;

use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class UserWishlistBlogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_wishlist_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UserWishlistBlogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UserWishlistBlogCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return UserWishlistDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the blog in user wishlist.'),
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('user_wishlist_id', 'wishlistId', UserWishlistDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the wishlist.'),
            new ManyToOneAssociationField('wishlist', 'user_wishlist_id', UserWishlistDefinition::class, 'id', false),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
        ]);
    }
}
