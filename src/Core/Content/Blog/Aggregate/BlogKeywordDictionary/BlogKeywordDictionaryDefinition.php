<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Language\LanguageDefinition;

class BlogKeywordDictionaryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_keyword_dictionary';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogKeywordDictionaryCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogKeywordDictionaryEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogKeywordDictionaryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of blog keyword.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new PrimaryKey(), new ApiAware(), new Required())->setDescription('Unique identity of the language.'),

            new StringField('keyword', 'keyword')->addFlags(new ApiAware(), new Required())->setDescription('The keywords that help to search the blog.'),
            new StringField('reversed', 'reversed')->addFlags(new Computed())->setDescription('The keywords are revered for the search.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
