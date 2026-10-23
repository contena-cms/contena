<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Extension\BlogSearchRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Tests\Examples\BlogSearchRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSearchRouteExtension::class)]
class BlogSearchRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesSearch(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new BlogSearchRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: BlogSearchRouteExtension::NAME,
            extension: new BlogSearchRouteExtension(new Request(), $context, $criteria),
            function: static function () use (&$coreCalled): BlogSearchRouteResponse {
                $coreCalled = true;

                return new BlogSearchRouteResponse(new BlogListingResult(
                    BlogDefinition::ENTITY_NAME,
                    0,
                    new BlogCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ));
            },
        );

        static::assertFalse($coreCalled, 'The core blog search must be skipped when a subscriber resolves it.');
        static::assertSame($criteria, $result->getListingResult()->getCriteria());
    }
}
