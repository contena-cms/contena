<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Service\BlogReviewCountService;

/**
 * @internal
 */
#[CoversClass(BlogReviewCountService::class)]
class BlogReviewCountServiceTest extends TestCase
{
    private BlogReviewCountService $blogReviewCountService;

    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->blogReviewCountService = new BlogReviewCountService($this->connection);
    }

    public function testUpdateReviewCountWithInvalidReviewIds(): void
    {
        $this->connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);
        $this->connection->expects($this->never())->method('executeStatement');

        $this->blogReviewCountService->updateReviewCount([]);
    }

    public function testUpdateReviewCount(): void
    {
        $this->connection->expects($this->once())->method('fetchFirstColumn')->willReturn(['foobar', 'barfoo']);
        $this->connection->expects($this->exactly(2))->method('executeStatement');

        $this->blogReviewCountService->updateReviewCount([]);
    }
}
