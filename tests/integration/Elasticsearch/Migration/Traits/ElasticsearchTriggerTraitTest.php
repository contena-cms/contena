<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Migration\Traits;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Elasticsearch\Migration\Traits\ElasticsearchTriggerTrait;

/**
 * @internal
 */
#[Package('inventory')]
class ElasticsearchTriggerTraitTest extends TestCase
{
    use ElasticsearchTriggerTrait;
    use IntegrationTestBehaviour;

    public function testTrigger(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $connection = self::getContainer()->get(Connection::class);

        $this->triggerElasticsearchIndexing($connection);

        static::assertSame('["*"]', $this->fetchConfig($connection));
    }

    public function fetchConfig(Connection $connection): string
    {
        return $connection->fetchOne('SELECT `value` FROM app_config WHERE `key` = "elasticsearch.indexing.entities"');
    }
}
