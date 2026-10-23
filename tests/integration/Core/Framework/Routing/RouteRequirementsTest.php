<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class RouteRequirementsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEveryRequirementNamesAPlaceholderOfItsRoute(): void
    {
        $unmatched = [];

        foreach (static::getContainer()->get('router')->getRouteCollection() as $name => $route) {
            $compiled = $route->compile();
            $placeholders = [...$compiled->getPathVariables(), ...$compiled->getHostVariables()];

            foreach (array_keys($route->getRequirements()) as $requirement) {
                if (!\in_array($requirement, $placeholders, true)) {
                    $unmatched[] = \sprintf('%s (%s): %s', $name, $route->getPath(), $requirement);
                }
            }
        }

        static::assertSame([], $unmatched, 'These route requirements name no placeholder of their route path or host.');
    }
}
