<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField;

use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\CustomField\CustomFieldDefinition;

class BlogSearchConfigFieldDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_search_config_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogSearchConfigFieldEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogSearchConfigFieldCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    public function getDefaults(): array
    {
        return [
            'tokenize' => false,
            'searchable' => false,
            'useExactSubfield' => false,
            'ranking' => 0,
        ];
    }

    public function getHydratorClass(): string
    {
        return BlogSearchConfigFieldHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogSearchConfigDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Blog Search Configuration field.'),
            new FkField('blog_search_config_id', 'searchConfigId', BlogSearchConfigDefinition::class)->addFlags(new Required())->setDescription('Unique identity of Search Configuration.'),
            new FkField('custom_field_id', 'customFieldId', CustomFieldDefinition::class)->setDescription('Unique identity of custom field.'),
            new StringField('field', 'field')->addFlags(new Required())->setDescription('Configuration of search field.'),
            new BoolField('tokenize', 'tokenize')->addFlags(new Required())->setDescription('To decide whether the text within the field should undergo tokenization, which involves splitting it into smaller chunks.'),
            new BoolField('searchable', 'searchable')->addFlags(new Required())->setDescription('To configure whether the field can be used for searching.'),
            new BoolField('use_exact_subfield', 'useExactSubfield')->addFlags(new Required())->setDescription('To configure whether exact match queries should target the exact subfield, which uses the whitespace analyzer (lowercased, whitespace-tokenised) and bypasses the language analyzer (no stemming, no stop-word removal, no compound decomposition).'),
            new IntField('ranking', 'ranking')->addFlags(new Required())->setDescription('Search ranking.'),
            new ManyToOneAssociationField('searchConfig', 'blog_search_config_id', BlogSearchConfigDefinition::class, 'id', false),
            new ManyToOneAssociationField('customField', 'custom_field_id', CustomFieldDefinition::class, 'id', false),
        ]);
    }
}
