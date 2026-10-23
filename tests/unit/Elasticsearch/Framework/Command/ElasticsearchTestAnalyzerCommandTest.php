<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Command;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Elasticsearch\Framework\Command\ElasticsearchTestAnalyzerCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ElasticsearchTestAnalyzerCommand::class)]
class ElasticsearchTestAnalyzerCommandTest extends TestCase
{
    #[TestDox('The term is analyzed with every analyzer and the tokens are printed')]
    public function testPrintsTokensPerAnalyzer(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->atLeastOnce())
            ->method('analyze')
            ->willReturnCallback(function (array $params): array {
                $this->assertSame('Contena Test', $params['body']['text']);

                return ['tokens' => [['token' => 'contena'], ['token' => 'test']]];
            });

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $commandTester = new CommandTester(new ElasticsearchTestAnalyzerCommand($client));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['term' => 'Contena Test']));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Default analyzers', $display);
        static::assertStringContainsString('standard', $display);
        static::assertStringContainsString('contena test', $display);
    }
}
