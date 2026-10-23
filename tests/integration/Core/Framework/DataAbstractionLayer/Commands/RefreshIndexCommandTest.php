<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Commands;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Console\Tester\CommandTester;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class RefreshIndexCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private RefreshIndexCommand $refreshIndexCommand;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->refreshIndexCommand = static::getContainer()->get(RefreshIndexCommand::class);
        $this->categoryRepository = static::getContainer()->get('category.repository');
    }

    public function testExecuteWithSkipIndexerOption(): void
    {
        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('channel.indexer', $message);
        static::assertStringContainsString('category.indexer', $message);

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => 'channel.indexer,category.indexer']);

        $message = $commandTester->getDisplay();

        static::assertStringNotContainsString('channel.indexer', $message);
        static::assertStringNotContainsString('category.indexer', $message);

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--only' => 'channel.indexer']);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('channel.indexer', $message);
        static::assertStringNotContainsString('category.indexer', $message);
    }

    public function testExecuteWithSkipSeoUpdaterOption(): void
    {
        if (!static::getContainer()->has(NavigationPageSeoUrlRoute::class)) {
            static::markTestSkipped('SeoUrl tests need frontend bundle to be installed');
        }
        $repo = static::getContainer()->get('seo_url.repository');
        $context = Context::createDefaultContext();
        $skip = 'channel.indexer,user.indexer,landing_page.indexer,payment_method.indexer,media.indexer,media_folder_configuration.indexer';
        $categoryA = $this->createCategoryWithoutSeoUrl();

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => $skip]);

        $seoUrl = $repo->search(
            (new Criteria())->addFilter(new EqualsFilter('pathInfo', \sprintf('/navigation/%s', $categoryA))),
            $context
        )->getEntities()->first();

        static::assertNotNull($seoUrl);

        $skip .= ',category.seo-url';
        $categoryB = $this->createCategoryWithoutSeoUrl();

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => $skip]);

        $seoUrl = $repo->search(
            (new Criteria())->addFilter(new EqualsFilter('pathInfo', \sprintf('/navigation/%s', $categoryB))),
            $context
        )->getEntities()->first();

        static::assertNull($seoUrl);
    }

    private function createCategoryWithoutSeoUrl(): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => Uuid::randomHex(),
            'parentId' => $this->getRootCategoryId(),
        ];

        $this->categoryRepository->upsert([$data], Context::createDefaultContext());

        static::getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM seo_url WHERE path_info = :pathInfo',
            ['pathInfo' => \sprintf('/navigation/%s', $id)]
        );

        return $id;
    }

    private function getRootCategoryId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        $firstCategoryId = $this->categoryRepository
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();
        static::assertNotNull($firstCategoryId);

        return $firstCategoryId;
    }
}
