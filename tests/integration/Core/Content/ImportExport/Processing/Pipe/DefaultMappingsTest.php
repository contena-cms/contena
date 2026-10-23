<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileEntity;
use Contena\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Contena\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class DefaultMappingsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDefaultMediaMapping(): void
    {
        $mediaTranslations = [
            'alt' => 'alternate text',
            'title' => 'media title',
        ];

        $media = [
            'id' => Uuid::randomHex(),
            'mediaFolderId' => Uuid::randomHex(),
            'url' => 'https://contena.test/foo/bar/media.png',
            'private' => false,
            'mediaType' => 'image/png',
            'translations' => [
                'DEFAULT' => $mediaTranslations,
            ],
        ];

        $mapping = $this->getDefaultMapping(MediaDefinition::ENTITY_NAME);

        $config = new Config($mapping, [], []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $pipeInResult = $mappingPipe->in($config, $media);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $mappedMedia = iterator_to_array($pipeInResult);

        static::assertSame($media['id'], $mappedMedia['id']);
        static::assertSame($media['mediaFolderId'], $mappedMedia['folder_id']);
        static::assertSame($media['url'], $mappedMedia['url']);
        static::assertSame($media['private'], $mappedMedia['private']);
        static::assertSame($media['mediaType'], $mappedMedia['type']);

        static::assertSame($mediaTranslations['alt'], $mappedMedia['alt']);
        static::assertSame($mediaTranslations['title'], $mappedMedia['title']);

        $pipeOutResult = $mappingPipe->out($config, $mappedMedia);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $unmappedMedia = iterator_to_array($pipeOutResult);
        $unmappedMediaTranslations = $unmappedMedia['translations']['DEFAULT'];

        static::assertSame($media['id'], $unmappedMedia['id']);
        static::assertSame($media['mediaFolderId'], $unmappedMedia['mediaFolderId']);
        static::assertSame($media['url'], $unmappedMedia['url']);
        static::assertSame($media['private'], $unmappedMedia['private']);
        static::assertSame($media['mediaType'], $unmappedMedia['mediaType']);

        static::assertSame($mediaTranslations['alt'], $unmappedMediaTranslations['alt']);
        static::assertSame($mediaTranslations['title'], $unmappedMediaTranslations['title']);
    }

    public function testBlogMapping(): void
    {
        $blog = [
            'id' => Uuid::randomHex(),
            'parentId' => Uuid::randomHex(),
            'blogNumber' => Uuid::randomHex(),
            'active' => false,
            'stock' => 10,

            'translations' => [
                'DEFAULT' => [
                    'name' => 'German',
                    'description' => 'Beschreibung',
                ],
            ],

            'price' => [
                'DEFAULT' => [
                    'gross' => 15,
                    'net' => 10,
                ],
            ],
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'cover' => [
                'media' => [
                    'id' => Uuid::randomHex(),
                    'mediaFolderId' => Uuid::randomHex(),
                    'url' => 'https://contena.test/foo/bar/media.png',
                    'private' => false,
                    'mediaType' => 'image/png',
                    'translations' => [
                        'DEFAULT' => [
                            'title' => 'cover media title',
                            'alt' => 'cover media alt',
                        ],
                    ],
                ],
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'Some brand',
                    ],
                ],
            ],
            'categories' => Uuid::randomHex() . '|' . Uuid::randomHex(),
            'visibilities' => [
                'all' => Uuid::randomHex() . '|' . Uuid::randomHex(),
            ],
        ];

        $mapping = $this->getDefaultMapping(BlogDefinition::ENTITY_NAME);

        $config = new Config($mapping, [], []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $pipeInResult = $mappingPipe->in($config, $blog);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $mappedBlog = iterator_to_array($pipeInResult);

        static::assertSame($blog['id'], $mappedBlog['id']);
        static::assertSame($blog['parentId'], $mappedBlog['parent_id']);

        static::assertSame($blog['blogNumber'], $mappedBlog['blog_number']);
        static::assertSame($blog['active'], $mappedBlog['active']);
        static::assertSame($blog['stock'], $mappedBlog['stock']);
        static::assertSame($blog['translations']['DEFAULT']['name'], $mappedBlog['name']);
        static::assertSame($blog['translations']['DEFAULT']['description'], $mappedBlog['description']);

        static::assertSame($blog['price']['DEFAULT']['net'], $mappedBlog['price_net']);
        static::assertSame($blog['price']['DEFAULT']['gross'], $mappedBlog['price_gross']);

        static::assertSame($blog['tax']['id'], $mappedBlog['tax_id']);
        static::assertSame($blog['tax']['taxRate'], $mappedBlog['tax_rate']);
        static::assertSame($blog['tax']['name'], $mappedBlog['tax_name']);

        static::assertSame($blog['cover']['media']['id'], $mappedBlog['cover_media_id']);
        static::assertSame($blog['cover']['media']['url'], $mappedBlog['cover_media_url']);
        static::assertSame($blog['cover']['media']['translations']['DEFAULT']['title'], $mappedBlog['cover_media_title']);
        static::assertSame($blog['cover']['media']['translations']['DEFAULT']['alt'], $mappedBlog['cover_media_alt']);

        static::assertSame($blog['manufacturer']['id'], $mappedBlog['manufacturer_id']);
        static::assertSame($blog['manufacturer']['translations']['DEFAULT']['name'], $mappedBlog['manufacturer_name']);

        static::assertSame($blog['categories'], $mappedBlog['categories']);
        static::assertSame($blog['visibilities']['all'], $mappedBlog['channel']);

        $pipeOutResult = $mappingPipe->out($config, $mappedBlog);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $unmappedBlog = iterator_to_array($pipeOutResult);

        static::assertSame($blog['id'], $unmappedBlog['id']);
        static::assertSame($blog['parentId'], $unmappedBlog['parentId']);

        static::assertSame($blog['blogNumber'], $unmappedBlog['blogNumber']);
        static::assertSame($blog['active'], $unmappedBlog['active']);
        static::assertSame($blog['stock'], $unmappedBlog['stock']);
        static::assertSame($blog['translations']['DEFAULT']['name'], $unmappedBlog['translations']['DEFAULT']['name']);
        static::assertSame($blog['translations']['DEFAULT']['description'], $unmappedBlog['translations']['DEFAULT']['description']);

        static::assertSame($blog['price']['DEFAULT']['net'], $unmappedBlog['price']['DEFAULT']['net']);
        static::assertSame($blog['price']['DEFAULT']['gross'], $unmappedBlog['price']['DEFAULT']['gross']);

        static::assertSame($blog['tax']['id'], $unmappedBlog['tax']['id']);
        static::assertSame($blog['tax']['taxRate'], $unmappedBlog['tax']['taxRate']);
        static::assertSame($blog['tax']['name'], $unmappedBlog['tax']['name']);

        static::assertSame($blog['cover']['media']['id'], $unmappedBlog['cover']['media']['id']);
        static::assertSame($blog['cover']['media']['url'], $unmappedBlog['cover']['media']['url']);
        static::assertSame($blog['cover']['media']['translations']['DEFAULT']['title'], $unmappedBlog['cover']['media']['translations']['DEFAULT']['title']);
        static::assertSame($blog['cover']['media']['translations']['DEFAULT']['alt'], $unmappedBlog['cover']['media']['translations']['DEFAULT']['alt']);

        static::assertSame($blog['manufacturer']['id'], $unmappedBlog['manufacturer']['id']);
        static::assertSame($blog['manufacturer']['translations']['DEFAULT']['name'], $unmappedBlog['manufacturer']['translations']['DEFAULT']['name']);

        static::assertSame($blog['categories'], $unmappedBlog['categories']);
        static::assertSame($blog['visibilities']['all'], $unmappedBlog['visibilities']['all']);
    }

    public function testCategoryMapping(): void
    {
        $category = [
            'id' => Uuid::randomHex(),
            'parentId' => Uuid::randomHex(),
            'active' => true,
            'type' => '',
            'visible' => true,
            'translations' => [
                'DEFAULT' => [
                    'name' => 'test',
                    'externalLink' => 'test',
                    'description' => 'test',
                    'metaTitle' => 'test',
                    'metaDescription' => 'test',
                ],
            ],
            'media' => [
                'id' => Uuid::randomHex(),
                'mediaFolderId' => Uuid::randomHex(),
                'url' => 'https://contena.test/foo/bar/media.png',
                'private' => false,
                'mediaType' => 'image/png',
                'translations' => [
                    'DEFAULT' => [
                        'title' => 'media title',
                        'alt' => 'media alt',
                    ],
                ],
            ],
            'cmsPageId' => Uuid::randomHex(),
        ];

        $mapping = $this->getDefaultMapping(CategoryDefinition::ENTITY_NAME);

        $config = new Config($mapping, [], []);
        $mappingPipe = new KeyMappingPipe($mapping, true);
        $pipeInResult = $mappingPipe->in($config, $category);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $mappedCategory = iterator_to_array($pipeInResult);

        static::assertSame($category['id'], $mappedCategory['id']);
        static::assertSame($category['parentId'], $mappedCategory['parent_id']);
        static::assertSame($category['active'], $mappedCategory['active']);

        static::assertSame($category['type'], $mappedCategory['type']);
        static::assertSame($category['visible'], $mappedCategory['visible']);
        static::assertSame($category['translations']['DEFAULT']['name'], $mappedCategory['name']);
        static::assertSame($category['translations']['DEFAULT']['externalLink'], $mappedCategory['external_link']);
        static::assertSame($category['translations']['DEFAULT']['description'], $mappedCategory['description']);
        static::assertSame($category['translations']['DEFAULT']['metaTitle'], $mappedCategory['meta_title']);
        static::assertSame($category['translations']['DEFAULT']['metaDescription'], $mappedCategory['meta_description']);

        static::assertSame($category['media']['id'], $mappedCategory['media_id']);
        static::assertSame($category['media']['url'], $mappedCategory['media_url']);
        static::assertSame($category['media']['mediaFolderId'], $mappedCategory['media_folder_id']);
        static::assertSame($category['media']['mediaType'], $mappedCategory['media_type']);
        static::assertSame($category['media']['translations']['DEFAULT']['title'], $mappedCategory['media_title']);
        static::assertSame($category['media']['translations']['DEFAULT']['alt'], $mappedCategory['media_alt']);

        static::assertSame($category['cmsPageId'], $mappedCategory['cms_page_id']);

        $pipeOutResult = $mappingPipe->out($config, $mappedCategory);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $unmappedCategory = iterator_to_array($pipeOutResult);

        static::assertSame($category['id'], $unmappedCategory['id']);
        static::assertSame($category['parentId'], $unmappedCategory['parentId']);
        static::assertSame($category['active'], $unmappedCategory['active']);

        static::assertSame($category['type'], $unmappedCategory['type']);
        static::assertSame($category['visible'], $unmappedCategory['visible']);
        static::assertSame($category['translations']['DEFAULT']['name'], $unmappedCategory['translations']['DEFAULT']['name']);
        static::assertSame($category['translations']['DEFAULT']['externalLink'], $unmappedCategory['translations']['DEFAULT']['externalLink']);
        static::assertSame($category['translations']['DEFAULT']['description'], $unmappedCategory['translations']['DEFAULT']['description']);
        static::assertSame($category['translations']['DEFAULT']['metaTitle'], $unmappedCategory['translations']['DEFAULT']['metaTitle']);
        static::assertSame($category['translations']['DEFAULT']['metaDescription'], $unmappedCategory['translations']['DEFAULT']['metaDescription']);

        static::assertSame($category['media']['id'], $unmappedCategory['media']['id']);
        static::assertSame($category['media']['url'], $unmappedCategory['media']['url']);
        static::assertSame($category['media']['mediaFolderId'], $unmappedCategory['media']['mediaFolderId']);
        static::assertSame($category['media']['mediaType'], $unmappedCategory['media']['mediaType']);
        static::assertSame($category['media']['translations']['DEFAULT']['title'], $unmappedCategory['media']['translations']['DEFAULT']['title']);
        static::assertSame($category['media']['translations']['DEFAULT']['alt'], $unmappedCategory['media']['translations']['DEFAULT']['alt']);

        static::assertSame($category['cmsPageId'], $unmappedCategory['cmsPageId']);
    }

    public function testNewsletterRecipient(): void
    {
        $mapping = $this->getDefaultMapping(NewsletterRecipientDefinition::ENTITY_NAME);

        $config = new Config($mapping, [], []);
        $mappingPipe = new KeyMappingPipe($mapping, true);

        $newsletterRecipient = [
            'id' => Uuid::randomHex(),
            'email' => 'email',
            'title' => 'title',
            'salutation' => [
                'salutationKey' => 'test',
            ],
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'zipCode' => '48624',
            'city' => 'Schöppingen',
            'street' => 'Ebbinghoff 10',
            'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
            'hash' => Uuid::randomHex(),
            'channel' => [
                'id' => Uuid::randomHex(),
            ],
        ];

        $pipeInResult = $mappingPipe->in($config, $newsletterRecipient);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $mappedNewsletterRecipient = iterator_to_array($pipeInResult);

        static::assertSame($newsletterRecipient['id'], $mappedNewsletterRecipient['id']);
        static::assertSame($newsletterRecipient['email'], $mappedNewsletterRecipient['email']);
        static::assertSame($newsletterRecipient['title'], $mappedNewsletterRecipient['title']);
        static::assertSame($newsletterRecipient['salutation']['salutationKey'], $mappedNewsletterRecipient['salutation']);
        static::assertSame($newsletterRecipient['firstName'], $mappedNewsletterRecipient['first_name']);
        static::assertSame($newsletterRecipient['lastName'], $mappedNewsletterRecipient['last_name']);
        static::assertSame($newsletterRecipient['zipCode'], $mappedNewsletterRecipient['zip_code']);
        static::assertSame($newsletterRecipient['city'], $mappedNewsletterRecipient['city']);
        static::assertSame($newsletterRecipient['street'], $mappedNewsletterRecipient['street']);
        static::assertSame($newsletterRecipient['status'], $mappedNewsletterRecipient['status']);
        static::assertSame($newsletterRecipient['hash'], $mappedNewsletterRecipient['hash']);
        static::assertSame($newsletterRecipient['channel']['id'], $mappedNewsletterRecipient['channel_id']);

        $pipeOutResult = $mappingPipe->out($config, $mappedNewsletterRecipient);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $unmappedNewsletterRecipient = iterator_to_array($pipeOutResult);

        static::assertSame($newsletterRecipient['id'], $unmappedNewsletterRecipient['id']);
        static::assertSame($newsletterRecipient['email'], $unmappedNewsletterRecipient['email']);
        static::assertSame($newsletterRecipient['title'], $unmappedNewsletterRecipient['title']);
        static::assertSame($newsletterRecipient['salutation']['salutationKey'], $unmappedNewsletterRecipient['salutation']['salutationKey']);
        static::assertSame($newsletterRecipient['firstName'], $unmappedNewsletterRecipient['firstName']);
        static::assertSame($newsletterRecipient['lastName'], $unmappedNewsletterRecipient['lastName']);
        static::assertSame($newsletterRecipient['zipCode'], $unmappedNewsletterRecipient['zipCode']);
        static::assertSame($newsletterRecipient['city'], $unmappedNewsletterRecipient['city']);
        static::assertSame($newsletterRecipient['street'], $unmappedNewsletterRecipient['street']);
        static::assertSame($newsletterRecipient['status'], $unmappedNewsletterRecipient['status']);
        static::assertSame($newsletterRecipient['hash'], $unmappedNewsletterRecipient['hash']);
        static::assertSame($newsletterRecipient['channel']['id'], $unmappedNewsletterRecipient['channel']['id']);
    }

    public function testBlogConfigurationSetting(): void
    {
        $mapping = $this->getDefaultMapping(BlogConfiguratorSettingDefinition::ENTITY_NAME);

        $config = new Config($mapping, [], []);
        $mappingPipe = new KeyMappingPipe($mapping, true);

        $setting = [
            'id' => Uuid::randomHex(),
            'blogId' => Uuid::randomHex(),
            'optionId' => Uuid::randomHex(),
            'position' => 1,
            'media' => [
                'id' => Uuid::randomHex(),
                'url' => 'https://contena.test/foo/bar.png',
                'mediaFolderId' => Uuid::randomHex(),
                'mediaType' => 'image/png',
                'translations' => [
                    'DEFAULT' => [
                        'title' => 'media title',
                        'alt' => 'media alt',
                    ],
                ],
            ],
            'price' => [
                'DEFAULT' => [
                    'gross' => 11.0,
                    'net' => 10.1,
                ],
            ],
        ];

        $pipeInResult = $mappingPipe->in($config, $setting);
        static::assertInstanceOf(\Traversable::class, $pipeInResult);
        $mappedSetting = iterator_to_array($pipeInResult);

        static::assertSame($setting['id'], $mappedSetting['id']);
        static::assertSame($setting['blogId'], $mappedSetting['blog_id']);
        static::assertSame($setting['optionId'], $mappedSetting['option_id']);
        static::assertSame($setting['position'], $mappedSetting['position']);

        static::assertSame($setting['media']['id'], $mappedSetting['media_id']);
        static::assertSame($setting['media']['url'], $mappedSetting['media_url']);
        static::assertSame($setting['media']['mediaFolderId'], $mappedSetting['media_folder_id']);
        static::assertSame($setting['media']['mediaType'], $mappedSetting['media_type']);
        static::assertSame($setting['media']['translations']['DEFAULT']['title'], $mappedSetting['media_title']);
        static::assertSame($setting['media']['translations']['DEFAULT']['alt'], $mappedSetting['media_alt']);

        static::assertSame($setting['price']['DEFAULT']['net'], $mappedSetting['price_net']);
        static::assertSame($setting['price']['DEFAULT']['gross'], $mappedSetting['price_gross']);

        $pipeOutResult = $mappingPipe->out($config, $mappedSetting);
        static::assertInstanceOf(\Traversable::class, $pipeOutResult);
        $unmappedSetting = iterator_to_array($pipeOutResult);

        static::assertSame($setting['id'], $unmappedSetting['id']);
        static::assertSame($setting['blogId'], $unmappedSetting['blogId']);
        static::assertSame($setting['optionId'], $unmappedSetting['optionId']);
        static::assertSame($setting['position'], $unmappedSetting['position']);

        static::assertSame($setting['media']['id'], $unmappedSetting['media']['id']);
        static::assertSame($setting['media']['url'], $unmappedSetting['media']['url']);
        static::assertSame($setting['media']['mediaFolderId'], $unmappedSetting['media']['mediaFolderId']);
        static::assertSame($setting['media']['mediaType'], $unmappedSetting['media']['mediaType']);
        static::assertSame($setting['media']['translations']['DEFAULT']['title'], $unmappedSetting['media']['translations']['DEFAULT']['title']);
        static::assertSame($setting['media']['translations']['DEFAULT']['alt'], $unmappedSetting['media']['translations']['DEFAULT']['alt']);

        static::assertSame($setting['price']['DEFAULT']['net'], $unmappedSetting['price']['DEFAULT']['net']);
        static::assertSame($setting['price']['DEFAULT']['gross'], $unmappedSetting['price']['DEFAULT']['gross']);
    }

    private function getDefaultMapping(string $entity): MappingCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sourceEntity', $entity));
        $criteria->addFilter(new EqualsFilter('systemDefault', true));

        $profile = static::getContainer()->get('import_export_profile.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ImportExportProfileEntity::class, $profile);
        $mapping = $profile->getMapping();
        static::assertIsArray($mapping);

        return MappingCollection::fromIterable($mapping);
    }
}
