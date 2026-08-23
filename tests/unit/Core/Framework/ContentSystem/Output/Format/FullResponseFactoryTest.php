<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(FullResponseFactory::class)]
class FullResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentRouteResponse wrapping the content page')]
    public function testCreateResponseReturnsContentRouteResponse(): void
    {
        $factory = new FullResponseFactory();
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentRouteResponse::class, $response);
        static::assertSame($page, $response->getContentPage());
    }
}
