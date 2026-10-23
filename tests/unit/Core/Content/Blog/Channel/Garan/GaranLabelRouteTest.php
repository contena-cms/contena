<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRoute;
use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRouteResponse;
use Contena\Core\Content\Blog\Extension\GaranLabelRouteExtension;
use Contena\Core\Content\Blog\Garan\GaranLabelResolver;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(GaranLabelRoute::class)]
class GaranLabelRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        /** @var StaticChannelRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticChannelRepository();

        $route = new GaranLabelRoute(
            $blogRepository,
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(new DecorationPatternException(GaranLabelRoute::class));

        $route->getDecorated();
    }

    public function testLoadRendersLabelForCompleteBlog(): void
    {
        $blogId = Uuid::randomHex();
        $blog = $this->createBlog($blogId, 'Acme', 'ACME-123', 36);

        $resolver = $this->createMock(GaranLabelResolver::class);
        $resolver->expects($this->exactly(2))
            ->method('resolve')
            ->with($blog, static::logicalOr(static::equalTo(GaranLabelResolver::LABEL_TYPE_FULL), static::equalTo(GaranLabelResolver::LABEL_TYPE_NESTED)))
            ->willReturn('<svg>rendered</svg>');

        /** @var StaticChannelRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticChannelRepository([new BlogCollection([$blog])]);

        $route = new GaranLabelRoute(
            $blogRepository,
            $resolver,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->load($blogId, Generator::generateChannelContext());

        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('svg'));
        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('nestedSvg'));
    }

    public function testLoadThrowsWhenBlogIsNotFound(): void
    {
        $blogId = Uuid::randomHex();

        /** @var StaticChannelRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticChannelRepository([new BlogCollection()]);

        $route = new GaranLabelRoute(
            $blogRepository,
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(BlogException::blogNotFound($blogId));

        $route->load($blogId, Generator::generateChannelContext());
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $context = Generator::generateChannelContext();
        $response = new GaranLabelRouteResponse('<svg>full</svg>', '<svg>nested</svg>');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('garan-label-route.load.pre', static function (GaranLabelRouteExtension $extension) use ($blogId, $context, $response): void {
            static::assertSame(['blogId' => $blogId, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new GaranLabelRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($blogId, $context));
    }

    private function createBlog(string $id, string $manufacturer, string $blogNumber, int $guaranteeMonths): ChannelBlogEntity
    {
        $manufacturerEntity = new BlogManufacturerEntity();
        $manufacturerEntity->setId(Uuid::randomHex());
        $manufacturerEntity->setName($manufacturer);

        $blog = new ChannelBlogEntity();
        $blog->setId($id);
        $blog->setBlogNumber($blogNumber);
        $blog->setManufacturer($manufacturerEntity);
        $blog->setGuaranteeMonths($guaranteeMonths);

        return $blog;
    }
}
