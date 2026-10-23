<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Command\ChannelCreateFrontendCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ChannelCreateFrontendCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    #[DataProvider('dataProviderTestExecuteCommandSuccess')]
    public function testExecuteCommandSuccessfully(string $isoCode, string $isoCodeExpected): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelCreateFrontendCommand::class));
        $url = 'http://localhost/' . Uuid::randomHex();

        $commandTester->execute([
            '--name' => 'Frontend',
            '--url' => $url,
            '--isoCode' => $isoCode,
        ]);

        $saleChannelId = $commandTester->getInput()->getOption('id');

        $countSaleChannelId = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM channel WHERE id = :id', ['id' => Uuid::fromHexToBytes($saleChannelId)]);

        static::assertSame(1, $countSaleChannelId);

        $getIsoCodeSql = <<<'SQL'
            SELECT snippet_set.iso
            FROM channel_domain
            JOIN snippet_set ON snippet_set.id = channel_domain.snippet_set_id
            WHERE channel_id = :saleChannelId
        SQL;
        $isoCodeResult = $this->connection->fetchOne($getIsoCodeSql, ['saleChannelId' => Uuid::fromHexToBytes($saleChannelId)]);

        static::assertSame($isoCodeExpected, $isoCodeResult);

        $commandTester->assertCommandIsSuccessful();
    }

    public static function dataProviderTestExecuteCommandSuccess(): \Generator
    {
        yield 'should success with valid iso code' => [
            'isoCode' => 'de_DE',
            'isoCodeExpected' => 'de-DE',
        ];

        yield 'should success with invalid iso code' => [
            'isoCode' => 'xy-XY',
            'isoCodeExpected' => 'en-GB',
        ];
    }
}
