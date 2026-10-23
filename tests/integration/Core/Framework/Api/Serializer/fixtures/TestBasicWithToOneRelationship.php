<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
class TestBasicWithToOneRelationship extends SerializationFixture
{
    private const USER_ID = '6f51622eb3814c75ae0263cece27ce72';
    private const LOCALE_ID = '0195146acfbd71038508c63b798b23b2';
    private const MEDIA_ID = '548faa1f7846436c85944f4aea792d96';

    public function getInput(): EntityCollection|Entity
    {
        $admin_user = new AdminUserEntity();
        $admin_user->setId(self::USER_ID);
        $admin_user->setName('Manufacturer');
        $admin_user->setLastName('');
        $admin_user->setPassword('password');
        $admin_user->setUsername('admin_user1');
        $admin_user->setActive(true);
        $admin_user->setAdmin(true);
        $admin_user->setEmail('admin_user1@shop.de');
        $admin_user->setCreatedAt(new \DateTime('2018-01-15T08:01:16.000+00:00'));
        $admin_user->internalSetEntityData('admin_user', new FieldVisibility([]));
        $admin_user->setLocaleId(self::LOCALE_ID);

        $media = new MediaEntity();
        $media->setId(self::MEDIA_ID);
        $media->setUserId(self::USER_ID);
        $media->setMimeType('image/jpg');
        $media->setFileExtension('jpg');
        $media->setFileSize(93889);
        $media->setTitle('2');
        $media->setCreatedAt(new \DateTime('2012-08-31T00:00:00.000+00:00'));
        $media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media->setUser($admin_user);
        $media->internalSetEntityData('media', new FieldVisibility([]));

        return $media;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                'id' => self::MEDIA_ID,
                'type' => 'media',
                'attributes' => [
                    'admin_userId' => self::USER_ID,
                    'mediaFolderId' => null,
                    'mimeType' => 'image/jpg',
                    'fileExtension' => 'jpg',
                    'uploadedAt' => null,
                    'fileName' => null,
                    'fileSize' => 93889,
                    'metaData' => null,
                    'mediaType' => null,
                    'createdAt' => '2012-08-31T00:00:00.000+00:00',
                    'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                    'alt' => null,
                    'title' => '2',
                    'url' => '',
                    'customFields' => null,
                    'hasFile' => false,
                    'translated' => [],
                    'private' => false,
                ],
                'links' => [
                    'self' => \sprintf('%s/media/%s', $baseUrl, self::MEDIA_ID),
                ],
                'relationships' => [
                    'admin_user' => [
                        'data' => [
                            'type' => 'admin_user',
                            'id' => self::USER_ID,
                        ],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/admin_user', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'categories' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/categories', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'blogManufacturers' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/blog-manufacturers', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'blogMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/blog-media', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'avatarUsers' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/avatar-users', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'translations' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/translations', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'thumbnails' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/thumbnails', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'mediaFolder' => [
                        'data' => null,
                        'links' => [
                            'related' => \sprintf('%s/media/%s/media-folder', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'propertyGroupOptions' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/property-group-options', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'tags' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/tags', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'mailTemplateMedia' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/mail-template-media', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'documentBaseConfigs' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/document-base-configs', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'shippingMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/shipping-methods', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'paymentMethods' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/payment-methods', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'blogConfiguratorSettings' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/blog-configurator-settings', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'orderLineItems' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/order-line-items', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'cmsBlocks' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/cms-blocks', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'cmsSections' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/cms-sections', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'cmsPages' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/cms-pages', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                    'documents' => [
                        'data' => [],
                        'links' => [
                            'related' => \sprintf('%s/media/%s/documents', $baseUrl, self::MEDIA_ID),
                        ],
                    ],
                ],
                'meta' => [],
            ],
            'included' => [
                [
                    'id' => self::USER_ID,
                    'type' => 'admin_user',
                    'attributes' => [
                        'localeId' => self::LOCALE_ID,
                        'avatarId' => null,
                        'username' => 'admin_user1',
                        'name' => 'Manufacturer',
                        'lastName' => '',
                        'email' => 'admin_user1@shop.de',
                        'active' => true,
                        'customFields' => null,
                        'createdAt' => '2018-01-15T08:01:16.000+00:00',
                        'updatedAt' => null,
                        'admin' => true,
                        'title' => null,
                    ],
                    'links' => [
                        'self' => \sprintf('%s/admin_user/%s', $baseUrl, self::USER_ID),
                    ],
                    'relationships' => [
                        'locale' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/locale', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'avatarMedia' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/avatar-media', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'media' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/media', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'accessKeys' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/access-keys', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'stateMachineHistoryEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/state-machine-history-entries', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'importExportLogEntries' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/import-export-log-entries', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'recoveryUser' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/recovery-admin_user', $baseUrl, self::USER_ID),
                            ],
                        ],
                        'aclRoles' => [
                            'data' => [],
                            'links' => [
                                'related' => \sprintf('%s/admin_user/%s/acl-roles', $baseUrl, self::USER_ID),
                            ],
                        ],
                    ],
                    'meta' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'id' => self::MEDIA_ID,
            'admin_userId' => self::USER_ID,
            'mimeType' => 'image/jpg',
            'fileExtension' => 'jpg',
            'fileSize' => 93889,
            'title' => '2',
            'metaData' => null,
            'mediaType' => null,
            'uploadedAt' => null,
            'alt' => null,
            'url' => '',
            'fileName' => null,
            'admin_user' => [
                'id' => self::USER_ID,
                'localeId' => self::LOCALE_ID,
                'avatarId' => null,
                'username' => 'admin_user1',
                'name' => 'Manufacturer',
                'lastName' => '',
                'email' => 'admin_user1@shop.de',
                'active' => true,
                'locale' => null,
                'avatarMedia' => null,
                'media' => null,
                'accessKeys' => null,
                'stateMachineHistoryEntries' => null,
                'importExportLogEntries' => null,
                'recoveryUser' => null,
                'customFields' => null,
                '_uniqueIdentifier' => self::USER_ID,
                'versionId' => null,
                'translated' => [],
                'createdAt' => '2018-01-15T08:01:16.000+00:00',
                'updatedAt' => null,
                'admin' => true,
                'title' => null,
                'aclRoles' => null,
                'apiAlias' => 'admin_user',
            ],
            'translations' => null,
            'categories' => null,
            'blogManufacturers' => null,
            'blogMedia' => null,
            'avatarUsers' => null,
            'thumbnails' => null,
            'mediaFolderId' => null,
            'mediaFolder' => null,
            'hasFile' => false,
            'private' => false,
            'propertyGroupOptions' => null,
            'mailTemplateMedia' => null,
            'customFields' => null,
            'tags' => null,
            'documentBaseConfigs' => null,
            'shippingMethods' => null,
            'paymentMethods' => null,
            'blogConfiguratorSettings' => null,
            'orderLineItems' => null,
            'cmsBlocks' => null,
            'cmsSections' => null,
            'cmsPages' => null,
            'documents' => null,
            '_uniqueIdentifier' => self::MEDIA_ID,
            'versionId' => null,
            'translated' => [],
            'createdAt' => '2012-08-31T00:00:00.000+00:00',
            'updatedAt' => '2017-11-21T11:25:34.000+00:00',
            'apiAlias' => 'media',
        ];
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedChannelJsonApiData(array $fixtures): array
    {
        unset(
            $fixtures['data']['attributes']['admin_userId'],
            $fixtures['data']['attributes']['mediaType'],
            $fixtures['data']['attributes']['mediaFolderId'],

            $fixtures['data']['relationships']['admin_user'],
            $fixtures['data']['relationships']['avatarUsers'],
            $fixtures['data']['relationships']['categories'],
            $fixtures['data']['relationships']['blogManufacturers'],
            $fixtures['data']['relationships']['blogMedia'],
            $fixtures['data']['relationships']['mediaFolder'],
            $fixtures['data']['relationships']['propertyGroupOptions'],
            $fixtures['data']['relationships']['mailTemplateMedia'],
            $fixtures['data']['relationships']['documentBaseConfigs'],
            $fixtures['data']['relationships']['shippingMethods'],
            $fixtures['data']['relationships']['paymentMethods'],
            $fixtures['data']['relationships']['tags'],
            $fixtures['data']['relationships']['blogConfiguratorSettings'],
            $fixtures['data']['relationships']['orderLineItems'],
            $fixtures['data']['relationships']['cmsBlocks'],
            $fixtures['data']['relationships']['cmsSections'],
            $fixtures['data']['relationships']['cmsPages'],
            $fixtures['data']['relationships']['documents'],

            $fixtures['included'][0]
        );

        return $fixtures;
    }

    /**
     * @param array<string, mixed> $fixtures
     *
     * @return array<string, mixed>
     */
    protected function removeProtectedChannelJsonData(array $fixtures): array
    {
        unset(
            $fixtures['admin_userId'],
            $fixtures['admin_user'],
            $fixtures['avatarUsers'],
            $fixtures['mediaType'],
            $fixtures['categories'],
            $fixtures['blogManufacturers'],
            $fixtures['blogMedia'],
            $fixtures['mediaFolderId'],
            $fixtures['mediaFolder'],
            $fixtures['propertyGroupOptions'],
            $fixtures['mailTemplateMedia'],
            $fixtures['tags'],
            $fixtures['documentBaseConfigs'],
            $fixtures['shippingMethods'],
            $fixtures['paymentMethods'],
            $fixtures['blogConfiguratorSettings'],
            $fixtures['orderLineItems'],
            $fixtures['cmsBlocks'],
            $fixtures['cmsSections'],
            $fixtures['cmsPages'],
            $fixtures['documents']
        );

        return $fixtures;
    }
}
