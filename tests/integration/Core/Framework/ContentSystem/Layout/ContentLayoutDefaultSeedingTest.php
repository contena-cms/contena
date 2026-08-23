<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class ContentLayoutDefaultSeedingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('seeds a type primitive default into a content layout written by a plain DAL create, so the stored tree is resolvable')]
    public function testPlainDalCreateSeedsPrimitiveDefaults(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        // A plain DAL create with the raw-array payload the Admin / Sync API and fixtures build, bypassing the
        // mutation ops: the element carries no headline, so only the write-boundary seeder can seed it.
        $this->repository()->create([[
            'id' => $id,
            'name' => 'seeder-test',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE, 'properties' => []]],
        ]], $context);

        $layout = $this->repository()->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        $tree = $layout->getLayout();
        static::assertCount(1, $tree);
        static::assertSame('Seeded headline', $tree[0]->getProperty('headline'));

        // Pass [] (a bound source contributing no root context), not null: a null root context skips the
        // binding-scope checks, so isResolvable() would hold trivially. [] runs them against the seeded primitive.
        static::assertTrue($this->diagnostics()->analyze($tree, [])->report->isResolvable());
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = $this->getContainer()->get(LayoutDiagnostics::class);
        static::assertInstanceOf(LayoutDiagnostics::class, $diagnostics);

        return $diagnostics;
    }
}
