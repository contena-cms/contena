<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
class TestMainResourceShouldNotBeInIncluded extends SerializationFixture
{
    private const USER_ID = 'f343a3c119cf42a7841aa0ac5094908c';
    private const LOCALE_ID = '0195146acfbd71038508c63b798b23b2';
    private const MEDIA_ID = '3e352be2d85846dd97529c0f6b544870';

    public function getInput(): EntityCollection|Entity
    {
        $admin_user = new AdminUserEntity();
        $admin_user->setId(self::USER_ID);
        $admin_user->setFirstName('Manufacturer');
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
        $media->setUser(clone $admin_user);
        $media->setUserId(self::USER_ID);
        $media->setMimeType('image/jpg');
        $media->setFileExtension('jpg');
        $media->setFileSize(18921);
        $media->setCreatedAt(new \DateTime('2012-08-15T00:00:00.000+00:00'));
        $media->setUpdatedAt(new \DateTime('2017-11-21T11:25:34.000+00:00'));
        $media->setTitle('Lagerkorn-5,0klein');
        $media->internalSetEntityData('media', new FieldVisibility([]));

        $mediaCollection = new MediaCollection([$media]);
        $admin_user->setMedia($mediaCollection);

        return $admin_user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                'id' => self::USER_ID,
                'type' => 'admin_user',
                'attributes' => [
                    'localeId' => self::LOCALE_ID,
                    'avatarId' => null,
                    'username' => 'admin_user1',
                    'firstName' => 'Manufacturer',
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
                        'data' => [
                            [
                                'type' => 'media',
                                'id' => self::MEDIA_ID,
                            ],
                        ],
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
            'included' => [
                [
                    'id' => self::MEDIA_ID,
                    'type' => 'media',
                    'attributes' => [
                        'admin_userId' => self::USER_ID,
                        'mediaFolderId' => null,
                        'mimeType' => 'image/jpg',
                        'fileExtension' => 'jpg',
                        'uploadedAt' => null,
                        'fileName' => null,
                        'fileSize' => 18921,
                        'metaData' => null,
                        'mediaType' => null,
                        'createdAt' => '2012-08-15T00:00:00.000+00:00',
                        'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                        'alt' => null,
                        'title' => 'Lagerkorn-5,0klein',
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
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'id' => self::USER_ID,
            'localeId' => self::LOCALE_ID,
            'avatarId' => null,
            'username' => 'admin_user1',
            'firstName' => 'Manufacturer',
            'lastName' => '',
            'email' => 'admin_user1@shop.de',
            'active' => true,
            'locale' => null,
            'avatarMedia' => null,
            'apiAlias' => 'admin_user',
            'media' => [
                [
                    'id' => self::MEDIA_ID,
                    'admin_userId' => self::USER_ID,
                    'mimeType' => 'image/jpg',
                    'fileExtension' => 'jpg',
                    'fileSize' => 18921,
                    'title' => 'Lagerkorn-5,0klein',
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
                        'firstName' => 'Manufacturer',
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
                    'createdAt' => '2012-08-15T00:00:00.000+00:00',
                    'updatedAt' => '2017-11-21T11:25:34.000+00:00',
                    'apiAlias' => 'media',
                ],
            ],
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
            $fixtures['data']['relationships']['recoveryUser'],
            $fixtures['data']['relationships']['aclRoles'],
            $fixtures['included'][0]['attributes']['admin_userId'],
            $fixtures['included'][0]['attributes']['admin_userId'],
            $fixtures['included'][0]['attributes']['admin_userId'],
            $fixtures['included'][0]['attributes']['mediaType'],
            $fixtures['included'][0]['attributes']['mediaFolderId'],

            $fixtures['included'][0]['relationships']['admin_user'],
            $fixtures['included'][0]['relationships']['avatarUsers'],
            $fixtures['included'][0]['relationships']['categories'],
            $fixtures['included'][0]['relationships']['blogManufacturers'],
            $fixtures['included'][0]['relationships']['blogMedia'],
            $fixtures['included'][0]['relationships']['mediaFolder'],
            $fixtures['included'][0]['relationships']['propertyGroupOptions'],
            $fixtures['included'][0]['relationships']['mailTemplateMedia'],
            $fixtures['included'][0]['relationships']['documentBaseConfigs'],
            $fixtures['included'][0]['relationships']['shippingMethods'],
            $fixtures['included'][0]['relationships']['paymentMethods'],
            $fixtures['included'][0]['relationships']['blogConfiguratorSettings'],
            $fixtures['included'][0]['relationships']['orderLineItems'],
            $fixtures['included'][0]['relationships']['cmsBlocks'],
            $fixtures['included'][0]['relationships']['cmsSections'],
            $fixtures['included'][0]['relationships']['cmsPages'],
            $fixtures['included'][0]['relationships']['documents']
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
            $fixtures['recoveryUser'],
            $fixtures['aclRoles'],
            $fixtures['media'][0]['admin_userId'],
            $fixtures['media'][0]['admin_user'],
            $fixtures['media'][0]['avatarUsers'],
            $fixtures['media'][0]['mediaType'],
            $fixtures['media'][0]['mediaFolderId'],
            $fixtures['media'][0]['categories'],
            $fixtures['media'][0]['blogManufacturers'],
            $fixtures['media'][0]['blogMedia'],
            $fixtures['media'][0]['mediaFolder'],
            $fixtures['media'][0]['propertyGroupOptions'],
            $fixtures['media'][0]['mailTemplateMedia'],
            $fixtures['media'][0]['documentBaseConfigs'],
            $fixtures['media'][0]['shippingMethods'],
            $fixtures['media'][0]['paymentMethods'],
            $fixtures['media'][0]['blogConfiguratorSettings'],
            $fixtures['media'][0]['orderLineItems'],
            $fixtures['media'][0]['cmsBlocks'],
            $fixtures['media'][0]['cmsSections'],
            $fixtures['media'][0]['cmsPages'],
            $fixtures['media'][0]['documents']
        );

        return $fixtures;
    }
}
