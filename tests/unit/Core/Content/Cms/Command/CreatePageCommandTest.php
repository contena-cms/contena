<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\Cms\Command\CreatePageCommand;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CreatePageCommand::class)]
class CreatePageCommandTest extends TestCase
{
    /**
     * @var StaticEntityRepository<CmsPageCollection>
     */
    private StaticEntityRepository $cmsPageRepository;

    private CreatePageCommand $command;

    protected function setUp(): void
    {
        /** @var StaticEntityRepository<BlogCollection> */
        $blogRepository = new StaticEntityRepository([
            [
                'blog-id-1',
                'blog-id-2',
            ],
        ], new BlogDefinition());

        /** @var StaticEntityRepository<CategoryCollection> */
        $categoryRepository = new StaticEntityRepository([
            [
                'category-id-1',
            ],
        ], new CategoryDefinition());

        /** @var StaticEntityRepository<MediaCollection> */
        $mediaRepository = new StaticEntityRepository([
            [
                'media-id-1',
            ],
        ], new MediaDefinition());

        $this->cmsPageRepository = new StaticEntityRepository([], new CmsPageDefinition());

        $this->command = new CreatePageCommand(
            $this->cmsPageRepository,
            $blogRepository,
            $categoryRepository,
            $mediaRepository
        );
    }

    public function testCreatePageWithoutResetOption(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $cmsPages = array_shift($this->cmsPageRepository->creates);
        static::assertIsArray($cmsPages);
        static::assertCount(1, $cmsPages);

        $cmsPage = array_shift($cmsPages);

        static::assertSame('landing_page', $cmsPage['type']);
        static::assertCount(4, $cmsPage['blocks']);

        // no deleted cms pages
        static::assertCount(0, $this->cmsPageRepository->deletes);

        static::assertSame(0, $commandTester->getStatusCode());
    }

    public function testCreatePageAndResetAllCmsPagesBefore(): void
    {
        $this->cmsPageRepository->addSearch([
            'deleted-page-id-1',
            'deleted-page-id-2',
            'deleted-page-id-3',
        ]);

        $commandTester = new CommandTester($this->command);

        $commandTester->execute([
            '--reset' => true,
        ]);

        $cmsPages = array_shift($this->cmsPageRepository->creates);
        static::assertIsArray($cmsPages);
        static::assertCount(1, $cmsPages);

        $cmsPage = array_shift($cmsPages);

        static::assertSame('landing_page', $cmsPage['type']);
        static::assertCount(4, $cmsPage['blocks']);

        static::assertSame([[
            ['id' => 'deleted-page-id-1'],
            ['id' => 'deleted-page-id-2'],
            ['id' => 'deleted-page-id-3'],
        ]], $this->cmsPageRepository->deletes);

        static::assertSame(0, $commandTester->getStatusCode());
    }
}
