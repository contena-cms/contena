<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\DocumentV2\DocumentCollection;
use Contena\Core\Checkout\DocumentV2\DocumentEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEvents;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class MediaRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/contena-logo.png';

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testPrivateMediaNotReadable(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'private' => true,
                ],
            ],
            $this->context
        );
        $mediaRepository = $this->mediaRepository;
        $media = null;
        $this->context->scope(Context::USER_SCOPE, function () use ($mediaId, &$media, $mediaRepository): void {
            $media = $mediaRepository->search(new Criteria([$mediaId]), $this->context);
        });

        static::assertNotNull($media);
        static::assertCount(0, $media->getEntities());
    }

    public function testDeletePrivateMedia(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $ids->get('media'),
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $ids->get('media') . '-' . (new \DateTime())->getTimestamp(),
                    'private' => true,
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 200,
                            'highDpi' => false,
                            'mediaThumbnailSize' => [
                                'width' => 100,
                                'height' => 200,
                            ],
                        ],
                    ],
                ],
            ],
            $context
        );

        $media = static::getContainer()->get('media.repository')
            ->search(new Criteria([$ids->get('media')]), $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        $fileSystem = static::getContainer()->get('contena.filesystem.private');

        $path = $media->getPath();

        // simulate file
        $fileSystem->write($path, 'foo');

        // ensure that file system knows the file
        static::assertTrue($fileSystem->has($path));

        $context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);
        static::getContainer()->get('media.repository')
            ->delete([['id' => $ids->get('media')]], $context);

        // after deleting the entity, the file should be deleted too
        static::assertFalse($fileSystem->has($path));
    }

    public function testPrivateMediaReadableThroughAssociation(): void
    {
        $documentId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->documentRepository->create(
            [
                [
                    'documentType' => [
                        'id' => $documentTypeId,
                        'technicalName' => 'testType',
                        'name' => 'test',
                    ],
                    'id' => $documentId,
                    'order' => $this->getOrderData($orderId),
                    'fileType' => 'pdf',
                    'config' => [],
                    'deepLinkCode' => 'deeplink',

                    'documentMediaFile' => [
                        'thumbnails' => [
                            [
                                'id' => Uuid::randomHex(),
                                'width' => 100,
                                'height' => 200,
                                'highDpi' => true,
                                'mediaThumbnailSize' => [
                                    'width' => 100,
                                    'height' => 200,
                                ],
                            ],
                        ],
                        'mediaFolder' => [
                            'id' => $folderId,
                            'name' => 'testFolder',
                            'configuration' => [
                                'id' => $configId,
                                'private' => true,
                            ],
                        ],

                        'id' => $mediaId,
                        'name' => 'test media',
                        'mimeType' => 'image/png',
                        'fileExtension' => 'png',
                        'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                        'private' => true,
                    ],
                ],
            ],
            $this->context
        );
        $mediaRepository = $this->mediaRepository;
        $media = $this->context->scope(Context::USER_SCOPE, static fn (Context $context) => $mediaRepository->search(new Criteria([$mediaId]), $context));

        static::assertInstanceOf(EntitySearchResult::class, $media);
        static::assertCount(0, $media->getEntities());

        $documentRepository = $this->documentRepository;
        $document = null;
        $this->context->scope(Context::USER_SCOPE, static function (Context $context) use (&$document, $documentId, $documentRepository): void {
            $criteria = new Criteria([$documentId]);
            $criteria->addAssociation('documentMediaFile');
            $document = $documentRepository->search($criteria, $context);
        });
        static::assertNotNull($document);
        static::assertCount(1, $document->getEntities());
        $document = $document->getEntities()->get($documentId);
        static::assertInstanceOf(DocumentEntity::class, $document);
        $media = $document->getDocumentMediaFile();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame($mediaId, $media->getId());
        static::assertSame('', $media->getUrl());
        // currently there shouldn't be loaded any thumbnails for private media, but if, the urls should be blank
        $thumbnails = $media->getThumbnails();
        static::assertNotNull($thumbnails);
        foreach ($thumbnails as $thumbnail) {
            static::assertSame('', $thumbnail->getUrl());
        }
    }

    public function testPublicMediaIsReadable(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'private' => false,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame($mediaId, $media->getId());
    }

    public function testDeleteMediaEntityWithoutThumbnails(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaPath = $media->getPath();

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaPath, $resource);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteMediaEntityWithThumbnails(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 200,
                            'highDpi' => true,
                            'mediaThumbnailSize' => [
                                'width' => 100,
                                'height' => 200,
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaPath = $media->getPath();

        static::assertNotNull($media->getThumbnails());
        static::assertNotNull($media->getThumbnails()->first());
        $thumbnailPath = $media->getThumbnails()->first()->getPath();

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaPath, $resource);
        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($thumbnailPath, $resource);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }

    public function testDeleteMediaDeletesOnlyFilesForGivenMediaId(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media.png',
                    'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
                ],
                [
                    'id' => $secondId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media_2.png',
                    'fileName' => $secondId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $read = $this->mediaRepository->search(
            new Criteria(
                [
                    $firstId,
                    $secondId,
                ]
            ),
            $this->context
        );
        $firstMedia = $read->getEntities()->get($firstId);
        static::assertInstanceOf(MediaEntity::class, $firstMedia);
        $secondMedia = $read->getEntities()->get($secondId);
        static::assertInstanceOf(MediaEntity::class, $secondMedia);

        $firstPath = $firstMedia->getPath();
        $secondPath = $secondMedia->getPath();

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($firstPath, $resource);

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($secondPath, $resource);

        $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
    }

    public function testDeleteForUnusedIds(): void
    {
        $firstId = Uuid::randomHex();

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        static::assertNull($event->getEventByEntityName(MediaDefinition::ENTITY_NAME));
    }

    public function testDeleteForEmptyIds(): void
    {
        $secondId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $secondId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $secondId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $read = $this->mediaRepository->search(new Criteria([$secondId]), $this->context);
        $secondMedia = $read->getEntities()->get($secondId);
        static::assertInstanceOf(MediaEntity::class, $secondMedia);

        $secondPath = $secondMedia->getPath();

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($secondPath, $resource);

        static::assertTrue($this->getPublicFilesystem()->has($secondPath));

        $event = $this->mediaRepository->delete([], $this->context);
        $this->runWorker();

        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
        static::assertNull($event->getEventByEntityName(MediaDefinition::ENTITY_NAME));
    }

    public function testDeleteForMediaWithoutFile(): void
    {
        $firstId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                ],
            ],
            $this->context
        );

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        $media = $event->getEventByEntityName(MediaDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $media);
        static::assertCount(1, $media->getIds());
        static::assertSame($firstId, $media->getIds()[0]);
    }

    public function testDeleteWithAlreadyDeletedFile(): void
    {
        $firstId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $firstId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        $this->runWorker();

        $media = $event->getEventByEntityName(MediaDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $media);
        static::assertCount(1, $media->getIds());
        static::assertSame($firstId, $media->getIds()[0]);
    }

    public function testItDoesNotDeleteFilesIfMediaHasDeleteRestrictions(): void
    {
        $cmsPageId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $fileName = $mediaId . '-' . (new \DateTime())->getTimestamp();

        $cmsPageRepository = static::getContainer()->get('cms_page.repository');

        $cmsPageRepository->create([[
            'id' => $cmsPageId,
            'name' => 'cms-page',
            'type' => 'page',
            'previewMedia' => [
                'id' => $mediaId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $fileName,
            ],
        ]], $this->context);

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaUrl = $media->getPath();

        $resource = fopen(self::FIXTURE_FILE, 'r');
        static::assertNotFalse($resource);

        $this->getPublicFilesystem()->writeStream($mediaUrl, $resource);

        try {
            $this->mediaRepository->delete([['id' => $mediaId]], $this->context);
            static::fail('asserted DeleteRestrictViolationException');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertSame(
                [
                    'entity' => 'media',
                    'usagesString' => 'cms_page (1)',
                    'usages' => [
                        [
                            'entityName' => 'cms_page',
                            'count' => 1,
                        ],
                    ],
                    'metaData' => [
                        'cms_page' => [
                            [
                                'id' => $cmsPageId,
                                'media' => [
                                    'id' => $mediaId,
                                    'fileExtension' => 'png',
                                    'fileName' => $fileName,
                                ],
                            ],
                        ],
                    ],
                ],
                $exception->getParameters()
            );
        }

        static::assertTrue($this->getPublicFilesystem()->has($mediaUrl));
    }

    public function testDeleteMediaEntityWithOrder(): void
    {
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );

        $order = $this->getOrderData($orderId, $mediaId);

        $this->orderRepository->create([$order], $this->context);

        $event = $this->mediaRepository->delete([['id' => $mediaId]], $this->context)->getEventByEntityName(OrderLineItemDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        $this->runWorker();

        $payload = $event->getPayloads()[0];

        static::assertSame(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, $event->getName());
        static::assertNull($payload['coverId']);
    }

    public function testPublicMediaUrlsAreReadableWithPartialDataLoading(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'private' => false,
                    'path' => 'http://some.domain/media.png',
                ],
            ],
            $this->context
        );
        $criteria = new Criteria([$mediaId]);
        $criteria->addFields(['id', 'url']);
        $media = $this->mediaRepository->search($criteria, $this->context)->getEntities()->get($mediaId);

        static::assertSame('http://some.domain/media.png', $media?->get('url'));
    }

    public function testDeleteExternalMediaDoesNotAttemptFilesystemDeletion(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'external media',
                    'private' => false,
                    'path' => 'https://localhost:8000/image.jpg',
                ],
            ],
            $this->context
        );

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('https://localhost:8000/image.jpg', $media->getPath());

        $this->context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);
        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $deletedMedia = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertNull($deletedMedia);
    }

    public function testDeleteExternalMediaWithExternalThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $thumbnailSizeId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'external media with thumbnails',
                    'private' => false,
                    'path' => 'https://localhost:8000/image.jpg',
                    'thumbnails' => [
                        [
                            'width' => 800,
                            'height' => 600,
                            'path' => 'https://localhost:8000/thumb-800.jpg',
                            'mediaThumbnailSize' => [
                                'id' => $thumbnailSizeId,
                                'width' => 800,
                                'height' => 600,
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );

        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('thumbnails');
        $media = $this->mediaRepository->search($criteria, $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('https://localhost:8000/image.jpg', $media->getPath());
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());
        static::assertCount(1, $media->getThumbnails());

        $thumbnail = $media->getThumbnails()->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);
        static::assertSame('https://localhost:8000/thumb-800.jpg', $thumbnail->getPath());

        $this->context->addState(MediaDeletionSubscriber::SYNCHRONE_FILE_DELETE);
        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $deletedMedia = $this->mediaRepository->search($criteria, $this->context)->getEntities()->get($mediaId);
        static::assertNull($deletedMedia);
    }

    public function testPathWithLiteralPercentIsPersistedAndUrlEncodedAsStorageKey(): void
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'fileName' => 'fifty-off',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'private' => false,
                    'path' => 'media/ab/cd/50%20off.png',
                    'thumbnails' => [
                        [
                            'width' => 100,
                            'height' => 100,
                            'path' => 'media/ab/cd/50%20off_100x100.png',
                            'mediaThumbnailSize' => [
                                'id' => Uuid::randomHex(),
                                'width' => 100,
                                'height' => 100,
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );

        $criteria = new Criteria([$mediaId]);
        $criteria->addAssociation('thumbnails');

        $media = Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->mediaRepository->search($criteria, $this->context)->getEntities()->get($mediaId));
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media/ab/cd/50%20off.png', $media->getPath());
        static::assertStringContainsString('/media/ab/cd/50%2520off.png?ts=', $media->getUrl());

        $thumbnail = $media->getThumbnails()?->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $thumbnail);
        static::assertSame('media/ab/cd/50%20off_100x100.png', $thumbnail->getPath());
        static::assertStringContainsString('/media/ab/cd/50%2520off_100x100.png', $thumbnail->getUrl());

        $legacyMedia = Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->mediaRepository->search($criteria, $this->context)->getEntities()->get($mediaId));
        static::assertInstanceOf(MediaEntity::class, $legacyMedia);
        static::assertStringContainsString('/media/ab/cd/50%20off.png?ts=', $legacyMedia->getUrl());
    }

    public function testAltTextLongerThan255CharactersIsRejected(): void
    {
        $mediaId = Uuid::randomHex();

        try {
            $this->mediaRepository->create([
                ['id' => $mediaId, 'name' => 'test media', 'alt' => str_repeat('a', 256)],
            ], $this->context);

            static::fail('An alt text longer than 255 characters must be rejected');
        } catch (WriteException $e) {
            $errors = iterator_to_array($e->getErrors());
            static::assertCount(1, $errors);
            static::assertStringEndsWith('/alt', $errors[0]['source']['pointer']);
        }

        $this->mediaRepository->create([
            ['id' => $mediaId, 'name' => 'test media', 'alt' => str_repeat('a', 255)],
        ], $this->context);

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->first();
        static::assertNotNull($media);
        static::assertSame(str_repeat('a', 255), $media->getAlt());
    }

    /**
     * @return array<string, mixed>
     */
    private function getOrderData(string $orderId, ?string $mediaId = null): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        return [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'channelId' => TestDefaults::CHANNEL,
            'deliveries' => [
                [
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ATOM),
                    'shippingDateLatest' => date(\DATE_ATOM),
                    'shippingOrderAddress' => [
                        'salutationId' => $salutation,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'country' => [
                            'name' => 'kasachstan',
                            'id' => $this->getValidCountryId(),
                        ],
                    ],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $orderLineItemId,
                        ],
                    ],
                ],
            ],
            'lineItems' => [
                [
                    'id' => $orderLineItemId,
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                    'good' => true,
                    'coverId' => $mediaId,
                ],
            ],
            'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
            'orderUser' => [
                'email' => 'test@example.com',
                'firstName' => 'Noe',
                'lastName' => 'Hill',
                'salutationId' => $salutation,
                'title' => 'Doc',
                'userNumber' => 'Test',
                'user' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'userNumber' => 'Test',
                    'guest' => true,
                    'group' => ['name' => 'testse2323'],
                    'channelId' => TestDefaults::CHANNEL,
                    'defaultBillingAddressId' => $addressId,
                    'defaultShippingAddressId' => $addressId,
                    'addresses' => [
                        [
                            'id' => $addressId,
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'countryStateId' => $countryStateId,
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $this->getValidCountryId(),
                                'states' => [
                                    [
                                        'id' => $countryStateId,
                                        'name' => 'oklahoma',
                                        'shortCode' => 'OH',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'salutationId' => $salutation,
                    'firstName' => 'Floy',
                    'lastName' => 'Glover',
                    'zipcode' => '59438-0403',
                    'city' => 'Stellaberg',
                    'street' => 'street',
                    'countryId' => $this->getValidCountryId(),
                    'id' => $addressId,
                ],
            ],
        ];
    }
}
