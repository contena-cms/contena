<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Contena\Core\Content\Product\Extension\ResolveListingExtension;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ResolveListingExtension::class)]
class ResolveListingExtensionTest extends TestCase
{
    public function testResolveListingExtension(): void
    {
        $responseBody = json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR);

        $mockHandler = new MockHandler([new Response(200, [], $responseBody)]);
        $handlerStack = HandlerStack::create($mockHandler);

        $history = [];
        $handlerStack->push(Middleware::history($history));

        $client = new Client(['handler' => $handlerStack]);

        $productRepo = StaticEntityRepository::of(ProductCollection::class, [
            [(new ProductEntity())->assign(['id' => 'plugin-id'])],
        ]);
        $example = new ResolveListingExample($client, $productRepo);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ResolveListingExtension(
            new Criteria(),
            static::createStub(SalesChannelContext::class),
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResolveListingExtension::NAME,
            extension: $extension,
            function: static function () {
                return new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([
                        (new ProductEntity())->assign(['id' => 'plugin-id']),
                    ]),
                    new AggregationResultCollection(),
                    new Criteria(),
                    Context::createDefaultContext()
                );
            }
        );

        static::assertInstanceOf(EntitySearchResult::class, $result);
        static::assertSame(['plugin-id'], array_values($result->getEntities()->getIds()));
        static::assertIsArray($history);
        static::assertCount(1, $history);

        $request = $history[0]['request'];
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
    }
}
