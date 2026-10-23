<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\BlogSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Exception\InvalidMediaUrlException;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogVisibilityCollection>
     */
    private EntityRepository $visibilityRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    /**
     * @var EntityRepository<BlogMediaCollection>
     */
    private EntityRepository $blogMediaRepository;

    /**
     * @var EntityRepository<BlogConfiguratorSettingCollection>
     */
    private EntityRepository $blogConfiguratorSettingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visibilityRepository = static::getContainer()->get('blog_visibility.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->blogMediaRepository = static::getContainer()->get('blog_media.repository');
        $this->blogConfiguratorSettingRepository = static::getContainer()->get('blog_configurator_setting.repository');
    }

    public function testOnlySupportsBlog(): void
    {
        $serializer = new BlogSerializer(
            $this->visibilityRepository,
            $this->channelRepository,
            $this->blogMediaRepository,
            $this->blogConfiguratorSettingRepository
        );

        static::assertTrue($serializer->supports('blog'), 'should support blog');

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            if ($entity !== 'blog') {
                static::assertFalse(
                    $serializer->supports($definition->getEntityName()),
                    BlogSerializer::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testBlogSerialize(): void
    {
        $blog = $this->getBlog();

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);

        $serializer = new BlogSerializer(
            $this->visibilityRepository,
            $this->channelRepository,
            $this->blogMediaRepository,
            $this->blogConfiguratorSettingRepository
        );
        $serializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], [], []), $blogDefinition, $blog));

        static::assertNotCount(0, $serialized);

        static::assertSame($blog->getId(), $serialized['id']);
        static::assertSame($blog->getTranslations()?->first()?->getName(), $serialized['translations']['DEFAULT']['name']);
        static::assertSame((string) $blog->getStock(), $serialized['stock']);
        static::assertSame($blog->getBlogNumber(), $serialized['blogNumber']);
        static::assertSame('1', $serialized['active']);
        static::assertStringContainsString('contena-logo.png', $serialized['cover']['media']['url']);
        static::assertStringContainsString('contena-icon.png', $serialized['media']);
        static::assertStringContainsString('contena-background.png', $serialized['media']);
        static::assertStringNotContainsString('contena-logo.png', $serialized['media']);

        $iterator = $serializer->deserialize(new Config([], [], []), $blogDefinition, $serialized);
        static::assertInstanceOf(\Traversable::class, $iterator);
        $deserialized = iterator_to_array($iterator);

        static::assertSame($blog->getId(), $deserialized['id']);
        $translations = $blog->getTranslations();
        static::assertNotNull($translations);

        $first = $translations->first();
        static::assertNotNull($first);
        static::assertSame($first->getName(), $deserialized['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($blog->getStock(), $deserialized['stock']);
        static::assertSame($blog->getBlogNumber(), $deserialized['blogNumber']);
        static::assertSame($blog->getActive(), $deserialized['active']);
    }

    public function testSupportsOnlyBlog(): void
    {
        $serializer = new BlogSerializer(
            $this->visibilityRepository,
            $this->channelRepository,
            $this->blogMediaRepository,
            $this->blogConfiguratorSettingRepository
        );

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === BlogDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    BlogDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testDeserializeBlogMedia(): void
    {
        $blog = $this->getBlog();

        $mediaService = static::createStub(MediaService::class);
        $expectedMediaFile = new MediaFile(
            '/tmp/foo/bar/contena-logo.png',
            'image/png',
            'png',
            1000,
            'bc0d90db4dd806bd671ae9f7fabc5796'
        );
        $mediaService->method('fetchFile')
            ->willReturnCallback(static function (Request $request) use ($expectedMediaFile): MediaFile {
                if ($request->query->get('url') === 'http://172.16.11.80/contena-logo.png') {
                    return $expectedMediaFile;
                }

                return new MediaFile(
                    '/tmp/foo/bar/baz',
                    'image/png',
                    'png',
                    1000,
                    Uuid::randomHex()
                );
            });

        $fileSaver = static::createStub(FileSaver::class);
        $mediaSerializer = new MediaSerializer(
            $mediaService,
            $fileSaver,
            static::getContainer()->get('media_folder.repository'),
            static::getContainer()->get('media.repository')
        );
        $mediaSerializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $serializerRegistry = static::createStub(SerializerRegistry::class);
        $serializerRegistry->method('getEntity')
            ->willReturn($mediaSerializer);
        $serializerRegistry->method('getFieldSerializer')
            ->willReturn(new FieldSerializer());

        $record = [
            'id' => $blog->getId(),
            'media' => 'http://172.16.11.80/contena-logo.png|http://172.16.11.80/contena-logo2.png',
        ];

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);

        $serializer = new BlogSerializer(
            $this->visibilityRepository,
            $this->channelRepository,
            $this->blogMediaRepository,
            $this->blogConfiguratorSettingRepository
        );
        $serializer->setRegistry($serializerRegistry);

        $result = $serializer->deserialize(new Config([], [], []), $blogDefinition, $record);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertSame($blog->getMedia()?->first()?->getId(), $result['media'][0]['id']);
        static::assertSame($blog->getMedia()?->first()?->getMedia()?->getId(), $result['media'][0]['media']['id']);
        static::assertArrayNotHasKey('url', $result['media'][0]['media']);

        static::assertArrayNotHasKey('id', $result['media'][1]);
    }

    public function testDeserializeBlogMediaWithInvalidUrl(): void
    {
        $record = [
            'media' => 'foo',
        ];

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);

        $serializer = new BlogSerializer(
            $this->visibilityRepository,
            $this->channelRepository,
            $this->blogMediaRepository,
            $this->blogConfiguratorSettingRepository
        );
        $serializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $result = $serializer->deserialize(new Config([], [], []), $blogDefinition, $record);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        static::assertArrayHasKey('_error', $result);
        static::assertInstanceOf(InvalidMediaUrlException::class, $result['_error']);
    }

    private function getBlog(): BlogEntity
    {
        $blogId = Uuid::randomHex();

        $blog = [
            'id' => $blogId,
            'stock' => 101,
            'blogNumber' => 'P101',
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test blog',
                ],
            ],
            'tax' => [
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'price' => [
                Defaults::CURRENCY => [
                    'gross' => 1.111,
                    'net' => 1.011,
                    'linked' => true,
                    'currencyId' => Defaults::CURRENCY,
                    'listPrice' => [
                        'gross' => 1.111,
                        'net' => 1.011,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test category',
                ],
            ],
            'cover' => [
                'id' => Uuid::randomHex(),
                'position' => 0,
                'media' => [
                    'id' => Uuid::randomHex(),
                    'fileName' => 'contena-logo',
                    'path' => 'contena-logo.png',
                    'fileExtension' => 'png',
                    'mimeType' => 'image/png',
                    'metaData' => [
                        'hash' => 'bc0d90db4dd806bd671ae9f7fabc5796',
                    ],
                ],
            ],
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 1,
                    'media' => [
                        'id' => Uuid::randomHex(),
                        'fileName' => 'contena-icon',
                        'path' => 'contena-icon.png',
                        'fileExtension' => 'png',
                        'mimeType' => 'image/png',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 2,
                    'media' => [
                        'id' => Uuid::randomHex(),
                        'fileName' => 'contena-background',
                        'path' => 'contena-background.png',
                        'fileExtension' => 'png',
                        'mimeType' => 'image/png',
                    ],
                ],
            ],
        ];

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([$blog], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addAssociation('translations');
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('media.media');
        $criteria->getAssociation('media')->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $blog = $blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        return $blog;
    }
}
