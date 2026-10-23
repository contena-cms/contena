<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Language\Channel\LanguageRoute;
use Contena\Core\System\Language\Channel\LanguageRouteResponse;
use Contena\Core\System\Language\Extension\LanguageRouteExtension;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LanguageRoute::class)]
class LanguageRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(LanguageRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('language-route.load.pre', static function (LanguageRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LanguageRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }
}
