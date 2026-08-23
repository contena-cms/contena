<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfig;

use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Language\LanguageDefinition;

class BlogSearchConfigDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_search_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogSearchConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogSearchConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    public function getDefaults(): array
    {
        return [
            'andLogic' => true,
            'minSearchLength' => 2,
            'excludedTerms' => [],
        ];
    }

    public function getHydratorClass(): string
    {
        return BlogSearchConfigHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Blog Search Configuration.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new Required())->setDescription('Unique identity of language.'),
            new BoolField('and_logic', 'andLogic')->addFlags(new Required())->setDescription('Blog search configuration with add logic.'),
            new IntField('min_search_length', 'minSearchLength')->addFlags(new Required())->setDescription('Minimum number of characters used for blog search.'),
            new ListField('excluded_terms', 'excludedTerms', StringField::class)->setDescription('Excluded terms in blog search.'),
            new OneToOneAssociationField('language', 'language_id', 'id', LanguageDefinition::class, false),
            new OneToManyAssociationField('configFields', BlogSearchConfigFieldDefinition::class, 'blog_search_config_id', 'id')->addFlags(new CascadeDelete()),
        ]);
    }
}
