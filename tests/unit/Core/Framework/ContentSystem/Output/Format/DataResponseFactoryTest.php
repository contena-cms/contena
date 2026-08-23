<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Channel\ContentDataRouteResponse;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(DataResponseFactory::class)]
class DataResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDataRouteResponse from content page')]
    public function testCreateResponseReturnsContentDataRouteResponse(): void
    {
        $factory = new DataResponseFactory(new DataLoaderConfigSerializerProvider(new ServiceLocator([])), new ConfigCanonicalizer());
        $root = ContentElementBuilder::create('section', 'r1')->withProperty('background', 'blue')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentDataRouteResponse::class, $response);
        $dataPage = $response->getContentDataPage();
        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('r1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
    }
}
