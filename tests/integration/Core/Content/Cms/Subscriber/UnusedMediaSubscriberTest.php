<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\Category\CategoryBuilder;
use Contena\Core\Content\Test\Cms\LayoutBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class UnusedMediaSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        try {
            $connection->fetchOne('SELECT JSON_OVERLAPS(JSON_ARRAY(1), JSON_ARRAY(1));');
        } catch (\Exception $e) {
            static::markTestSkipped('JSON_OVERLAPS() function not supported on this database');
        }

        parent::setUp();

        $this->cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
    }

    public function testMediaIdsAreNotRemovedWhenMediaIsNotReferenced(): void
    {
        $ids = new IdsCollection();
        $media = [];
        foreach (range(1, 10) as $i) {
            $media[] = [
                'id' => $ids->create('media-' . $i),
                'fileName' => "Media $i",
                'fileExtension' => 'jpg',
                'mimeType' => 'image/jpeg',
                'fileSize' => 12345,
            ];
        }
        $this->mediaRepository->create($media, Context::createDefaultContext());

        $mediaIds = array_values($ids->all());

        $event = new UnusedMediaSearchEvent($mediaIds, Context::createDefaultContext());
        $listener = new UnusedMediaSubscriber(static::getContainer()->get(Connection::class));

        $listener->removeUsedMedia($event);

        static::assertSame($mediaIds, $event->getUnusedIds());
    }

    public function testMediaIdsFromAllPossibleLocationsAreRemovedFromEvent(): void
    {
        $mediaIds = $this->createContent();
        $event = new UnusedMediaSearchEvent($mediaIds, Context::createDefaultContext());
        $listener = new UnusedMediaSubscriber(static::getContainer()->get(Connection::class));

        $listener->removeUsedMedia($event);

        static::assertCount(0, $event->getUnusedIds());
    }

    /**
     * @return list<string>
     */
    private function createContent(): array
    {
        $ids = new IdsCollection();
        $media = [];
        foreach (range(1, 15) as $i) {
            $media[] = [
                'id' => $ids->create('media-' . $i),
                'fileName' => "Media $i",
                'fileExtension' => 'jpg',
                'mimeType' => 'image/jpeg',
                'fileSize' => 12345,
            ];
        }
        $this->mediaRepository->create($media, Context::createDefaultContext());

        $mediaIds = $ids->all();

        $pages = [];
        $pages[] = (new LayoutBuilder($ids, 'page-1'))
            ->image('media-1')
            ->image('media-2')
            ->build();

        $pages[] = (new LayoutBuilder($ids, 'page-2'))
            ->imageSlider(['media-3', 'media-4'])
            ->imageSlider(['media-4', 'media-5'])
            ->imageGallery(['media-6', 'media-7', 'media-8'])
            ->build();

        // create a blog with image slider, and image override
        $blog = (new BlogBuilder($ids, 'blog-1'))
            ->price(100)
            ->layout('page-2')
            ->slot(
                'slot-1',
                [
                    'sliderItems' => [
                        'source' => 'static',
                        'value' => array_map(static fn (string $id) => ['mediaId' => $id], array_values($ids->getList(['media-9', 'media-10']))),
                    ],
                    'speed' => ['source' => 'static', 'value' => 300],
                    'autoSlide' => ['source' => 'static', 'value' => false],
                    'minHeight' => ['source' => 'static', 'value' => '300px'],
                    'displayMode' => ['source' => 'static', 'value' => 'standard'],
                    'verticalAlign' => ['source' => 'static', 'value' => null],
                    'navigationDots' => ['source' => 'static', 'value' => true],
                    'autoplayTimeout' => ['source' => 'static', 'value' => 5000],
                    'navigationArrows' => ['source' => 'static', 'value' => 'outside'],
                ],
            )
            ->slot(
                'slot-2',
                [
                    'media' => [
                        'value' => $ids->get('media-11'),
                        'source' => 'static',
                    ],
                    'url' => ['value' => null, 'source' => 'static'],
                    'newTab' => ['value' => false, 'source' => 'static'],
                    'minHeight' => ['source' => 'static', 'value' => '300px'],
                    'displayMode' => ['value' => 'standard', 'source' => 'static'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
            )
            ->slot(
                'slot-3',
                [
                    'media' => [
                        'value' => $ids->get('media-12'),
                        'source' => 'static',
                    ],
                    'url' => ['value' => null, 'source' => 'static'],
                    'newTab' => ['value' => false, 'source' => 'static'],
                    'minHeight' => ['source' => 'static', 'value' => '300px'],
                    'displayMode' => ['value' => 'standard', 'source' => 'static'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
            )
            ->build();

        // create a category with image slider, and image override
        $category = (new CategoryBuilder($ids, 'category-1'))
            ->layout('my-category-layout')
            ->slot(
                'slot-1',
                [
                    'sliderItems' => [
                        'source' => 'static',
                        'value' => array_map(static fn (string $id) => ['mediaId' => $id], array_values($ids->getList(['media-13', 'media-14']))),
                    ],
                    'speed' => ['source' => 'static', 'value' => 300],
                    'autoSlide' => ['source' => 'static', 'value' => false],
                    'minHeight' => ['source' => 'static', 'value' => '300px'],
                    'displayMode' => ['source' => 'static', 'value' => 'standard'],
                    'verticalAlign' => ['source' => 'static', 'value' => null],
                    'navigationDots' => ['source' => 'static', 'value' => true],
                    'autoplayTimeout' => ['source' => 'static', 'value' => 5000],
                    'navigationArrows' => ['source' => 'static', 'value' => 'outside'],
                ],
            )
            ->slot(
                'slot-2',
                [
                    'media' => [
                        'value' => $ids->get('media-15'),
                        'source' => 'static',
                    ],
                    'url' => ['value' => null, 'source' => 'static'],
                    'newTab' => ['value' => false, 'source' => 'static'],
                    'minHeight' => ['source' => 'static', 'value' => '300px'],
                    'displayMode' => ['value' => 'standard', 'source' => 'static'],
                    'verticalAlign' => ['value' => null, 'source' => 'static'],
                ],
            )
            ->build();

        $this->cmsPageRepository->create($pages, Context::createDefaultContext());
        $this->blogRepository->create([$blog], Context::createDefaultContext());
        $this->categoryRepository->create([$category], Context::createDefaultContext());

        return array_values($mediaIds);
    }
}
