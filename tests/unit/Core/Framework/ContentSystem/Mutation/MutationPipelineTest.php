<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Contena\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Contena\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;

/**
 * @internal
 */
#[CoversClass(MutationPipeline::class)]
class MutationPipelineTest extends TestCase
{
    #[TestDox('applies the mutation and returns the mutated layout, affected ids and report')]
    public function testRunReturnsMutatedLayout(): void
    {
        $mutated = new ContentElement('new-1', 'CT:Card');
        $report = new DiagnosticsReport([]);

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis($report, ['new-1' => []])), new PageContextConsumerWiring());

        $result = $pipeline->run($this->mutation([$mutated], ['new-1']), [new ContentElement('el-1', 'CT:Block')], null);

        static::assertSame([$mutated], $result->layout);
        static::assertSame(['new-1'], $result->affectedElementIds);
        static::assertSame($report, $result->diagnostics);
    }

    #[TestDox('restricts the returned resolutions to the affected elements')]
    public function testRunRestrictsResolutionsToAffected(): void
    {
        $resolutions = [
            'new-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
            'other' => [new PropertyResolution('title', PropertyKind::Primitive, false, 'string', 'x')],
        ];

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)), new PageContextConsumerWiring());

        $result = $pipeline->run($this->mutation([new ContentElement('new-1', 'CT:Card')], ['new-1']), [new ContentElement('el-1', 'CT:Block')], null);

        static::assertSame(['new-1'], array_keys($result->resolutions));
    }

    #[TestDox('returns no resolutions when the mutation affects nothing')]
    public function testRunReturnsEmptyResolutionsWhenNothingAffected(): void
    {
        $resolutions = [
            'new-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
        ];

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)), new PageContextConsumerWiring());

        $result = $pipeline->run($this->mutation([new ContentElement('new-1', 'CT:Card')], []), [new ContentElement('el-1', 'CT:Block')], null);

        static::assertSame([], $result->resolutions);
    }

    #[TestDox('passes orphaned subtrees from the op through to the result')]
    public function testRunCarriesOrphaned(): void
    {
        $orphan = new ContentElement('orphan', 'CT:Block');

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])), new PageContextConsumerWiring());

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'CT:New')], ['el-1'], [$orphan]), [new ContentElement('el-1', 'CT:Block')], null);

        static::assertSame([$orphan], $result->orphaned);
    }

    #[TestDox('passes dropped wiring keys from the op through to the result')]
    public function testRunCarriesDroppedWiring(): void
    {
        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])), new PageContextConsumerWiring());

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'CT:New')], ['el-1'], [], ['legacy']), [new ContentElement('el-1', 'CT:Block')], null);

        static::assertSame(['legacy'], $result->droppedWiring);
    }

    #[TestDox('forwards the mutated tree and root context to the diagnostics pass')]
    public function testRunForwardsArgumentsToDiagnostics(): void
    {
        $mutated = new ContentElement('new-1', 'CT:Card');
        $rootContext = [new ProvidedContext('blog', 'Some\\Entity', ContextType::Single, null, DistributionStrategy::Broadcast)];

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with([$mutated], $rootContext)
            ->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));

        $pipeline = new MutationPipeline($diagnostics, new PageContextConsumerWiring());

        $pipeline->run($this->mutation([$mutated], ['new-1']), [new ContentElement('el-1', 'CT:Block')], $rootContext);
    }

    #[TestDox('wires page-context consumers into the mutated layout')]
    public function testRunWiresContextConsumers(): void
    {
        $article = new ContentElement('a1', 'CT:Blog:Card');
        $resolutions = ['a1' => [new PropertyResolution('blog', PropertyKind::Reference, false, null, null, 'Contena\\Core\\Content\\Blog\\BlogEntity')]];
        $rootContext = [new ProvidedContext('blog', 'Contena\\Core\\Content\\Blog\\BlogEntity', ContextType::Single, null, DistributionStrategy::Broadcast)];

        $pipeline = new MutationPipeline(
            $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)),
            new PageContextConsumerWiring(),
        );

        $result = $pipeline->run($this->mutation([$article], ['a1']), [new ContentElement('el-1', 'CT:Block')], $rootContext);

        static::assertArrayHasKey('blog', $result->layout[0]->getAcceptsContext());
    }

    /**
     * @param list<ContentElement> $appliedTree
     * @param list<string> $affected
     * @param list<ContentElement> $orphaned
     * @param list<string> $droppedWiring
     */
    private function mutation(array $appliedTree, array $affected, array $orphaned = [], array $droppedWiring = []): LayoutMutation
    {
        $mutation = static::createStub(LayoutMutation::class);
        $mutation->method('apply')->willReturn($appliedTree);
        $mutation->method('affected')->willReturn($affected);
        $mutation->method('orphaned')->willReturn($orphaned);
        $mutation->method('droppedWiring')->willReturn($droppedWiring);

        return $mutation;
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }
}
