<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cleanup;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupBlogKeywordDictionaryTask::class)]
final class CleanupBlogKeywordDictionaryTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        LoggerInterface $logger,
        private readonly Connection $connection
    ) {
        parent::__construct($repository, $logger);
    }

    public function run(): void
    {
        do {
            $result = RetryableQuery::retryable(
                $this->connection,
                fn (): int => (int) $this->connection->executeStatement(
                    'DELETE FROM blog_keyword_dictionary WHERE keyword NOT IN (SELECT DISTINCT keyword FROM blog_search_keyword) LIMIT 1000',
                )
            );
        } while ($result > 0);
    }
}
