<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Elasticsearch\Blog\StopwordTokenFilter;

/**
 * @internal
 */
class StopwordTokenFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testExcludedFilterFilter(array $tokens, array $expected): void
    {
        $service = new StopwordTokenFilter($this->connection);
        $keywords = $service->filter($tokens, $this->context);

        sort($expected);
        sort($keywords);
        static::assertSame($expected, $keywords);
    }

    /**
     * @return array<array{list<string>, list<string>}>
     */
    public static function cases(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
            ],
            [
                ['i', '', 'ky', 'u', 'ag', 'vn'],
                ['ky', 'ag', 'vn'],
            ],
        ];
    }
}
