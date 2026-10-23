<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Locale\LocaleDefinition;

/**
 * @codeCoverageIgnore
 */
class AppAdministrationSnippetDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_administration_snippet';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppAdministrationSnippetCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppAdministrationSnippetEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.15.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
