<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Contena\Core\Content\Flow\Dispatching\BufferedFlow;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Contena\Core\Content\Flow\Dispatching\FlowExecutor;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(BufferedFlowExecutor::class)]
class BufferedFlowExecutorTest extends TestCase
{
    public function testLoadsFlowsWithEachBufferedEventsContext(): void
    {
        $tenantAContext = Context::createTenantContext('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $tenantBContext = Context::createTenantContext('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $queue = new BufferedFlowQueue();
        $queue->queueFlow(new BufferedFlow('tenant-a-event', $tenantAContext, []));
        $queue->queueFlow(new BufferedFlow('tenant-b-event', $tenantBContext, []));

        $loader = $this->createMock(AbstractFlowLoader::class);
        $loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static function (Context $context) use ($tenantAContext, $tenantBContext): array {
                static $invocation = 0;
                $expected = [$tenantAContext, $tenantBContext][$invocation++];
                TestCase::assertSame($expected, $context);

                return [];
            });

        $executor = new BufferedFlowExecutor(
            $queue,
            $loader,
            new FlowFactory([]),
            static::createStub(FlowExecutor::class),
            new NullLogger(),
        );

        $executor->executeBufferedFlows();

        static::assertTrue($queue->isEmpty());
    }
}
