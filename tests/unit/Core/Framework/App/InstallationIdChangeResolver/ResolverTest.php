<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\InstallationIdChangeResolver\InstallationIdChangeStrategy;
use Contena\Core\Framework\App\InstallationIdChangeResolver\Resolver;
use Contena\Core\Framework\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Resolver::class)]
class ResolverTest extends TestCase
{
    private MockObject&InstallationIdChangeStrategy $firstStrategy;

    private MockObject&InstallationIdChangeStrategy $secondStrategy;

    private Resolver $appUrlChangedResolverStrategy;

    protected function setUp(): void
    {
        $this->firstStrategy = $this->createMock(InstallationIdChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(InstallationIdChangeStrategy::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->appUrlChangedResolverStrategy = new Resolver([
            $this->firstStrategy,
            $this->secondStrategy,
        ]);
    }

    public function testItCallsRightStrategy(): void
    {
        $this->firstStrategy->expects($this->once())
            ->method('resolve');

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects($this->never())
            ->method('resolve');

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $this->expectExceptionObject(AppException::installationIdChangeResolveStrategyNotFound('ThirdStrategy'));
        $this->appUrlChangedResolverStrategy->resolve('ThirdStrategy', Context::createDefaultContext());
    }

    public function testGetAvailableStrategies(): void
    {
        $this->firstStrategy->expects($this->once())
            ->method('getDescription')
            ->willReturn('first description');

        $this->secondStrategy->expects($this->once())
            ->method('getDescription')
            ->willReturn('second description');

        static::assertSame([
            'FirstStrategy' => 'first description',
            'SecondStrategy' => 'second description',
        ], $this->appUrlChangedResolverStrategy->getAvailableStrategies());
    }
}
