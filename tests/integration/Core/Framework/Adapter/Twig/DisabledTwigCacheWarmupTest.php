<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class DisabledTwigCacheWarmupTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceIsRemoved(): void
    {
        static::expectException(ServiceNotFoundException::class);
        static::getContainer()->get('twig.template_cache_warmer');
    }
}
