<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Routing\Router as SymfonyRouter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(Router::class)]
class RouterTest extends TestCase
{

    /**
     * @return \Generator<list<UrlCase>>
     */
    public static function urlCases(): \Generator
    {
        $id = Uuid::randomHex();

        yield 'test-home-page-without-suffix' => [
        ];
        yield 'test-home-page-with-de' => [
        ];
        yield 'test-home-page-with-de-and-slash' => [
        ];
        yield 'test-home-page-with-de-without-slash' => [
        ];
        yield 'test-home-page-with-null' => [
        ];
        yield 'test-navigation-page-with-de' => [
        ];
        yield 'test-home-page-without-suffix-relative' => [
        ];
        yield 'test-home-page-with-de-relative' => [
        ];
        yield 'test-home-page-with-de-and-slash-relative' => [
        ];
        yield 'test-home-page-with-de-without-slash-relative' => [
        ];
        yield 'test-home-page-with-null-relative' => [
        ];
        yield 'test-navigation-page-with-de-relative' => [
        ];
        yield 'test-home-page-without-suffix-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', '', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-and-slash-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de/', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-without-slash-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', 'de', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-null-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', null, 'frontend.home.page'),
        ];
        yield 'test-navigation-page-with-de-absolute-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
        ];
        yield 'test-home-page-without-suffix-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', '', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-and-slash-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de/', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-de-without-slash-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', 'de', 'frontend.home.page'),
        ];
        yield 'test-home-page-with-null-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', null, 'frontend.home.page'),
        ];
        yield 'test-navigation-page-with-de-network-path' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
        ];
        yield 'test-home-page-without-suffix-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-and-slash-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-without-slash-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-null-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-navigation-page-with-de-absolute-url-with-port' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
        ];
        yield 'test-home-page-without-suffix-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-and-slash-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-de-without-slash-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-home-page-with-null-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
        ];
        yield 'test-navigation-page-with-de-network-path-with-port' => [
            new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
        ];
        yield 'test-custom-route-allowed-with-base-url' => [
            new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/custom-route', '/de', 'custom.route', allowedRoutes: ['custom.route']),
        ];
    }
}

/**
 * @internal
 */
class UrlCase
{
    /**
     * @param array<string, string> $params
     * @param list<string> $allowedRoutes
     */
    public function __construct(
        public int $type,
        public string $expected,
        public ?string $baseUrl,
        public string $route,
        public array $params = [],
        public string $host = 'test.de',
        public array $allowedRoutes = [],
    ) {
    }
}
