<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class SwSanitizeTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SwSanitizeTwigFilter $swSanitize;

    protected function setUp(): void
    {
        $filter = static::getContainer()->get(SwSanitizeTwigFilter::class);
        static::assertInstanceOf(SwSanitizeTwigFilter::class, $filter);
        $this->swSanitize = $filter;
    }

    public function testTwigFilterIsRegistered(): void
    {
        $filters = $this->swSanitize->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('sw_sanitize', $filters[0]->getName());
    }
}
