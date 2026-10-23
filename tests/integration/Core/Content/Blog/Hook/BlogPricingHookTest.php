<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Hook;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Blog\Hook\Pricing\BlogPricingHook;
use Contena\Core\Content\Blog\Hook\Pricing\BlogProxy;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Script\Execution\ScriptLoader;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class BlogPricingHookTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testScripts(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            (new BlogBuilder($ids, 'p1'))
                ->visibility()
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'p2'))
                ->price(100)
                ->visibility()
                ->prices('rule-A', 50)
                ->prices('rule-A', 30, 'default', null, 10)
                ->prices('rule-A', 15, 'default', null, 20)
                ->build(),
            (new BlogBuilder($ids, 'p3'))
                ->price(100)
                ->visibility()
                ->variant((new BlogBuilder($ids, 'p3.1'))->price(50)->build())
                ->variant((new BlogBuilder($ids, 'p3.2'))->price(40)->build())
                ->build(),
        ];

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $channelContext->setRuleIds([$ids->get('rule-A')]);

        $blogs = static::getContainer()->get('channel.blog.repository')
            ->search(new Criteria($ids->getList(['p1', 'p2', 'p3.1'])), $channelContext)
            ->getEntities();

        $stubs = static::getContainer()->get(ScriptPriceStubs::class);

        $p1 = $blogs->get($ids->get('p1'));
        $p2 = $blogs->get($ids->get('p2'));
        $p3 = $blogs->get($ids->get('p3.1'));

        static::assertInstanceOf(Entity::class, $p1);
        static::assertInstanceOf(Entity::class, $p2);
        static::assertInstanceOf(Entity::class, $p3);

        $proxies = [
            $ids->get('p1') => new BlogProxy($p1, $channelContext, $stubs),
            $ids->get('p2') => new BlogProxy($p2, $channelContext, $stubs),
            $ids->get('p3.1') => new BlogProxy($p3, $channelContext, $stubs),
        ];

        $channelContext->considerInheritance();
        $hook = new BlogPricingHookExtension($proxies, $channelContext, $ids);

        // allows easy debugging
        $traces = new ScriptTraces(new NativeClock());

        $loader = static::createStub(ScriptLoader::class);
        $loader->method('get')->willReturn([
            new Script('foo', (string) \file_get_contents(__DIR__ . '/_fixtures/pricing-cases/blog-pricing.twig'), new \DateTimeImmutable()),
        ]);

        $executor = new ScriptExecutor(
            $loader,
            $traces,
            static::getContainer(),
            static::getContainer()->get(ScriptEnvironmentFactory::class),
        );

        $executor->execute($hook);

        static::assertNotCount(0, $traces->getTraces());
        static::assertArrayHasKey('blog-pricing', $traces->getTraces());
        static::assertSame(
            [
                'original' => 100.0,
                'changed' => 1.5,
                'plus' => 3.0,
                'minus' => 1.5,
                'discount' => 1.35,
                'surcharge' => 1.49,
                'price-20' => 15.0,
                'price-30' => 10.0,
                'price-31' => 5.0,
                'name' => 'p2',
                'cheapest' => 40.0,
                'cheapest.change' => 15.0,
                'cheapest.reset' => 50.0,
                'cheapest.discount' => 45.0,
                'cheapest.surcharge' => 49.5,
                'cheapest.minus' => 48.5,
                'cheapest.plus' => 49.5,
            ],
            $traces->getOutput('blog-pricing', 0)
        );
    }
}

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class BlogPricingHookExtension extends BlogPricingHook
{
    /**
     * @param BlogProxy[] $blogs
     */
    public function __construct(
        array $blogs,
        ChannelContext $channelContext,
        public readonly IdsCollection $ids
    ) {
        parent::__construct($blogs, $channelContext);
    }
}
