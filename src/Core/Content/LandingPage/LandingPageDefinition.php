<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel\LandingPageChannelDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Tag\TagDefinition;

class LandingPageDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'landing_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LandingPageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LandingPageEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField()->addFlags(new ApiAware()),
            new BoolField('active', 'active')->addFlags(new ApiAware()),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslatedField('metaTitle')->addFlags(new ApiAware()),
            new TranslatedField('metaDescription')->addFlags(new ApiAware()),
            new TranslatedField('keywords')->addFlags(new ApiAware()),
            new TranslatedField('url')->addFlags(new ApiAware()),
            new TranslationsAssociationField(LandingPageTranslationDefinition::class, 'landing_page_id')->addFlags(new ApiAware(), new Required()),
            new ManyToManyAssociationField('tags', TagDefinition::class, LandingPageTagDefinition::class, 'landing_page_id', 'tag_id')->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('channels', ChannelDefinition::class, LandingPageChannelDefinition::class, 'landing_page_id', 'channel_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key')->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the landing page across different channels'),
        ]);
    }
}
