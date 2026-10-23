<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Detail;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Detail\AvailableCombinationLoader;
use Contena\Core\Content\Blog\Stock\AbstractStockStorage;
use Contena\Core\Content\Blog\Stock\StockDataCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AvailableCombinationLoader::class)]
class AvailableCombinationLoaderTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getAvailableCombinationLoader()->getDecorated();
    }

    public function testLoadCombinationsReturnsAvailableCombinationResult(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::generateChannelContext($context);
        $loader = $this->getAvailableCombinationLoader();
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        $combinations = $result->getCombinations();
        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
            'a6a23a74867cad90ee0c788a48944911' => [
                'green',
            ],
        ], $combinations);
    }

    public function testLoadCombinationsReturnsAvailableCombinationResultWithAvailabilityFromStockStorage(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::generateChannelContext($context);

        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())
            ->method('load')
            ->willReturn(new StockDataCollection([
            ]));

        $loader = $this->getAvailableCombinationLoader($stockStorage);
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        $combinations = $result->getCombinations();
        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
            'a6a23a74867cad90ee0c788a48944911' => [
                'green',
            ],
        ], $combinations);

        static::assertFalse($result->isAvailable(['green', 'red']));
        static::assertFalse($result->isAvailable(['green']));
    }

    public function testLoadCombinationsHidesCloseoutVariantsWhenConfigured(): void
    {
        $context = Context::createDefaultContext();
        $salesChanelContext = Generator::generateChannelContext($context);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getBool')
            ->willReturn(true);

        $loader = $this->getAvailableCombinationLoader(
            systemConfigService: $systemConfigService
        );
        $result = $loader->loadCombinations(
            Uuid::randomHex(),
            $salesChanelContext
        );

        static::assertSame([
            '4b97f87ff3bd2cd72cc6f6f7d2ae49ae' => [
                'green',
                'red',
            ],
        ], $result->getCombinations());
    }


    private function getMockedConnection(): Connection
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            [
                'id' => 'blog-1',
                'available' => true,
                'isCloseout' => false,
                'options' => json_encode([
                    'green',
                    'red',
                ]),
            ],
            [
                'id' => 'blog-2',
                'available' => false,
                'isCloseout' => true,
                'options' => json_encode([
                    'green',
                ]),
            ],
            [
                'id' => 'invalid',
                'available' => false,
                'isCloseout' => false,
                'options' => '{ bar: "baz" }',
            ],
        ]);

        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
