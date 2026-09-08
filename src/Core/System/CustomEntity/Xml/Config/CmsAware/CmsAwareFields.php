<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Config\CmsAware;

use Contena\Core\System\CustomEntity\Xml\Field\Field;
use Contena\Core\System\CustomEntity\Xml\Field\JsonField;
use Contena\Core\System\CustomEntity\Xml\Field\ManyToManyField;
use Contena\Core\System\CustomEntity\Xml\Field\ManyToOneField;
use Contena\Core\System\CustomEntity\Xml\Field\StringField;
use Contena\Core\System\CustomEntity\Xml\Field\TextField;

/**
 * @internal
 */
class CmsAwareFields
{
    /**
     * @return list<Field>
     */
    public static function getCmsAwareFields(): array
    {
        return [
            StringField::fromArray(['name' => 'ct_title', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            TextField::fromArray(['name' => 'ct_content', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            ManyToOneField::fromArray(['name' => 'ct_cms_page', 'reference' => 'cms_page', 'channelApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),
            JsonField::fromArray(['name' => 'ct_slot_config', 'channelApiAware' => true, 'required' => false]),
            ManyToManyField::fromArray(['name' => 'ct_categories', 'reference' => 'category', 'channelApiAware' => true, 'required' => false, 'onDelete' => 'cascade']),

            // SEO fields
            StringField::fromArray(['name' => 'ct_seo_meta_title', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            StringField::fromArray(['name' => 'ct_seo_meta_description', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            StringField::fromArray(['name' => 'ct_seo_url', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            StringField::fromArray(['name' => 'ct_og_title', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            StringField::fromArray(['name' => 'ct_og_description', 'channelApiAware' => true, 'required' => false, 'translatable' => true]),
            ManyToOneField::fromArray(['name' => 'ct_og_image', 'reference' => 'media', 'channelApiAware' => true, 'required' => false, 'onDelete' => 'set-null']),
        ];
    }
}
