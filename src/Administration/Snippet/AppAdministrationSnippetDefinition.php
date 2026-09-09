<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new LongTextField('value', 'value')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING), new AllowEmptyString(), new AllowHtml()),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('locale_id', 'localeId', LocaleDefinition::class)->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
