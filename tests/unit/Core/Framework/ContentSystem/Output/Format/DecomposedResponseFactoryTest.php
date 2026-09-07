<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use Contena\Core\Framework\ContentSystem\Channel\ContentDecomposedRouteResponse;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\Format\DecomposedResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DecomposedResponseFactory::class)]
class DecomposedResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDecomposedRouteResponse carrying the render result')]
    public function testCreateResponseReturnsContentDecomposedRouteResponse(): void
    {
        $factory = new DecomposedResponseFactory();

        $result = new RenderResult([], LayoutReference::create('layout-1', 'Test', null), null);

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentDecomposedRouteResponse::class, $response);
        static::assertSame($result, $response->getRenderResult());
    }
}
