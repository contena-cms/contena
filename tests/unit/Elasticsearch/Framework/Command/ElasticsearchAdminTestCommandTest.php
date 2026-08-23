<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminTestCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ElasticsearchAdminTestCommand::class)]
class ElasticsearchAdminTestCommandTest extends TestCase
{
    #[TestDox('The search result totals are printed per admin index')]
    public function testPrintsSearchResultPerIndex(): void
    {
        $searcher = $this->createMock(AdminSearcher::class);
        $searcher
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (string $term, array $entities): array {
                $this->assertSame('contena', $term);
                $this->assertContains(BlogDefinition::ENTITY_NAME, $entities);

                return [
                    'blog' => [
                        'total' => 5,
                        'data' => [],
                        'indexer' => 'blog-listing',
                        'index' => 'ct-admin-blog',
                    ],
                ];
            });

        $commandTester = new CommandTester(new ElasticsearchAdminTestCommand($searcher));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['term' => 'contena']));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('ct-admin-blog', $display);
        static::assertStringContainsString('blog-listing', $display);
        static::assertStringContainsString('5', $display);
    }
}
