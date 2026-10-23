<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCompressor;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\CartSerializationCleaner;
use Contena\Core\Checkout\Cart\Command\CartMigrateCommand;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\RedisCartPersister;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
class CartMigrateCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $redisUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($this->redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }
    }

    public function testWithRedisPrefix(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $redisCart = new Cart(Uuid::randomHex());
        $redisCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getChannelContext($redisCart->getToken());

        $factory = new RedisConnectionFactory('test-prefix-');
        $redis = $factory->create($this->redisUrl);
        static::assertInstanceOf(\Redis::class, $redis);
        $redis->flushAll();

        $persister = new RedisCartPersister($redis, static::getContainer()->get('event_dispatcher'), static::getContainer()->get(CartSerializationCleaner::class), new CartCompressor(false, 'gzip'), 90);
        $persister->save($redisCart, $context);

        $command = new CartMigrateCommand($redis, static::getContainer()->get(Connection::class), 90, $factory, new CartCompressor(false, 'gzip'), new NativeClock());
        $command->run(new ArrayInput(['from' => 'redis']), new NullOutput());

        $persister = new CartPersister(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(CartSerializationCleaner::class),
            new CartCompressor(false, 'gzip'),
            new NativeClock()
        );

        $persister->load($redisCart->getToken(), $context);
    }

    #[DataProvider('dataProvider')]
    public function testRedisToSql(bool $sqlCompressed, bool $redisCompressed): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $redisCart = new Cart(Uuid::randomHex());
        $redisCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getChannelContext($redisCart->getToken());

        $factory = static::getContainer()->get(RedisConnectionFactory::class);
        $redis = $factory->create($this->redisUrl);
        static::assertInstanceOf(\Redis::class, $redis);
        $redis->flushAll();

        $persister = new RedisCartPersister($redis, static::getContainer()->get('event_dispatcher'), static::getContainer()->get(CartSerializationCleaner::class), new CartCompressor($redisCompressed, 'gzip'), 90);
        $persister->save($redisCart, $context);

        $command = new CartMigrateCommand($redis, static::getContainer()->get(Connection::class), 90, $factory, new CartCompressor($redisCompressed, 'gzip'), new NativeClock());
        $command->run(new ArrayInput(['from' => 'redis']), new NullOutput());

        $persister = new CartPersister(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(CartSerializationCleaner::class),
            new CartCompressor($sqlCompressed, 'gzip'),
            new NativeClock()
        );

        $persister->load($redisCart->getToken(), $context);
    }

    #[DataProvider('dataProvider')]
    public function testSqlToRedis(bool $sqlCompressed, bool $redisCompressed): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $sqlCart = new Cart(Uuid::randomHex());
        $sqlCart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->getChannelContext($sqlCart->getToken());

        $persister = new CartPersister(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(CartSerializationCleaner::class),
            new CartCompressor(false, 'gzip'),
            new NativeClock()
        );

        $persister->save($sqlCart, $context);

        $token = static::getContainer()->get(Connection::class)->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $sqlCart->getToken()]);
        static::assertNotFalse($token);

        $factory = static::getContainer()->get(RedisConnectionFactory::class);
        $redis = $factory->create($this->redisUrl);
        static::assertInstanceOf(\Redis::class, $redis);
        $redis->flushAll();

        $command = new CartMigrateCommand($redis, static::getContainer()->get(Connection::class), 90, $factory, new CartCompressor($sqlCompressed, 'gzip'), new NativeClock());
        $command->run(new ArrayInput(['from' => 'sql']), new NullOutput());

        $persister = new RedisCartPersister($redis, static::getContainer()->get('event_dispatcher'), static::getContainer()->get(CartSerializationCleaner::class), new CartCompressor($redisCompressed, 'gzip'), 90);
        $persister->load($sqlCart->getToken(), $context);
    }

    public static function dataProvider(): \Generator
    {
        yield 'Test sql compressed and redis compressed' => [true, true];
        yield 'Test sql uncompressed and redis uncompressed' => [false, false];
        yield 'Test sql uncompressed and redis compressed' => [false, true];
        yield 'Test sql compressed and redis uncompressed' => [true, false];
    }

    private function getChannelContext(string $token): ChannelContext
    {
        return static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL);
    }
}
