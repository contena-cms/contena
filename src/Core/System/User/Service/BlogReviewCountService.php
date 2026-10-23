<?php declare(strict_types=1);

namespace Contena\Core\System\User\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @final
 */
class BlogReviewCountService
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $reviewIds
     */
    public function updateReviewCount(array $reviewIds): void
    {
        /** @var list<string> $affectedUsers */
        $affectedUsers = array_filter($this->connection->fetchFirstColumn(
            'SELECT DISTINCT(`user_id`) FROM blog_review WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($reviewIds)],
            ['ids' => ArrayParameterType::BINARY]
        ));

        foreach ($affectedUsers as $userId) {
            $this->updateReviewCountForUser($userId);
        }
    }

    public function updateReviewCountForUser(string $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE `user` SET review_count = (
                  SELECT COUNT(*) FROM `blog_review` WHERE `user_id` = :id AND `status` = 1
            ) WHERE id = :id',
            ['id' => $userId]
        );
    }
}
