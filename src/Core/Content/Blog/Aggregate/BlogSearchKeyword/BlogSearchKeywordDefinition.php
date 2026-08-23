<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Language\LanguageDefinition;

class BlogSearchKeywordDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_search_keyword';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogSearchKeywordCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogSearchKeywordEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogSearchKeywordHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Blog Search Keyword.'),
            new VersionField(),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of language.'),
            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new Required())->setDescription('Unique identity of Blog.'),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new Required()),
            new StringField('keyword', 'keyword')->addFlags(new Required())->setDescription('The keywords that help to search the blog.'),
            new FloatField('ranking', 'ranking')->addFlags(new Required())->setDescription('Search ranking.'),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }
}
