<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\FindVariant;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FindBlogVariantRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private ChannelContext $context;

    private FindBlogVariantRoute $findBlogVariantRoute;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $this->context = static::getContainer()->get(ChannelContextFactory::class)
            ->create('test', TestDefaults::CHANNEL);

        $this->findBlogVariantRoute = static::getContainer()->get(FindBlogVariantRoute::class);

        $this->ids = new IdsCollection();

        $this->createBlog();

        parent::setUp();
    }

    public function testFindVariant(): void
    {
        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        $switched = $this->ids->get('Color');

        $result = $this->findBlogVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redXL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindToNotCombinable(): void
    {
        // update red-xl to inactive
        $this->repository->update(
            [
                ['id' => $this->ids->get('redXL'), 'active' => false],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->ids->get('Color');

        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        // wished to switch to red-xl but this variant is not available (active = false).
        // should switch to next matching size
        $result = $this->findBlogVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindSkipsHiddenCloseoutVariantsWhenConfigured(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $this->repository->update(
            [
                ['id' => $this->ids->get('redXL'), 'stock' => 0, 'isCloseout' => true],
            ],
            Context::createDefaultContext()
        );

        $switched = $this->ids->get('Color');

        $options = [
            $this->ids->get('Color') => $this->ids->get('Red'),
            $this->ids->get('Size') => $this->ids->get('XL'),
        ];

        $result = $this->findBlogVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );

        static::assertSame($this->ids->get('redL'), $result->getFoundCombination()->getVariantId());
    }

    public function testFindNoCombinable(): void
    {
        $switched = $this->ids->get('new');

        $options = [
            $this->ids->get('new') => $this->ids->get('new'),
        ];

        $this->expectExceptionObject(BlogException::variantNotFound(
            $this->ids->get('base'),
            [$this->ids->get('new') => $this->ids->get('new')]
        ));

        $this->findBlogVariantRoute->load(
            $this->ids->get('base'),
            new Request(
                [
                    'switchedGroup' => $switched,
                    'options' => $options,
                ]
            ),
            $this->context
        );
    }

    private function createBlog(): void
    {
        (new BlogBuilder($this->ids, 'base', 10))->configuratorSetting(
            'Red',
            'Color'
        )->configuratorSetting(
            'Green',
            'Color'
        )->configuratorSetting(
            'XL',
            'Size'
        )->configuratorSetting(
            'L',
            'Size'
        )->visibility()->price(10)->write(static::getContainer());

        (new BlogBuilder($this->ids, 'redXL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('XL', 'Size')->stock(10)->write(static::getContainer());
        (new BlogBuilder($this->ids, 'greenXL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('XL', 'Size')->stock(10)->write(static::getContainer());
        (new BlogBuilder($this->ids, 'redL', 10))->visibility()->parent('base')->price(10)->option(
            'Red',
            'Color'
        )->option('L', 'Size')->stock(10)->write(static::getContainer());
        (new BlogBuilder($this->ids, 'greenL', 10))->visibility()->parent('base')->price(10)->option(
            'Green',
            'Color'
        )->option('L', 'Size')->stock(10)->write(static::getContainer());
    }
}
