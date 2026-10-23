<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

/**
 * @internal
 */
class FakeQueryBuilder extends QueryBuilder
{
    private readonly Connection $connection;

    /**
     * @param list<array<array-key, mixed>> $dbRows
     */
    public function __construct(
        Connection $connection,
        private readonly array $dbRows
    ) {
        parent::__construct($connection);
        $this->connection = $connection;
    }

    public function execute(): Result|int|string
    {
        return FakeResultFactory::createResult($this->dbRows, $this->connection);
    }

    public function executeQuery(): Result
    {
        return FakeResultFactory::createResult($this->dbRows, $this->connection);
    }
}
