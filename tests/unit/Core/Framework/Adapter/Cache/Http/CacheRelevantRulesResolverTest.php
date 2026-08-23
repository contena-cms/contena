<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Contena\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CacheRelevantRulesResolver::class)]
class CacheRelevantRulesResolverTest extends TestCase
{
    public function testResolveRuleAreas(): void
    {
        $eventDispatcher = new AssertingEventDispatcher(
            $this,
            [
                ResolveCacheRelevantRuleIdsExtension::NAME . '.pre' => 1,
                ResolveCacheRelevantRuleIdsExtension::NAME . '.post' => 1,
            ]
        );

        $resolver = new CacheRelevantRulesResolver(new ExtensionDispatcher($eventDispatcher));

        $ruleAreas = $resolver->resolveRuleAreas(
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertSame([], $ruleAreas);
    }
}
