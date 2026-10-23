<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCompressor;
use Contena\Core\Checkout\Cart\CartSerializationCleaner;
use Contena\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\RedisCartPersister;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
class RedisCartPersisterTest extends TestCase
{
    private RedisCartPersister $persister;

    private \Redis $redis;

    protected function setUp(): void
    {
        parent::setUp();

        $redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }

        $client = (new RedisConnectionFactory())->create($redisUrl);
        static::assertInstanceOf(\Redis::class, $client);
        $this->redis = $client;
        $this->persister = new RedisCartPersister($this->redis, new CollectingEventDispatcher(), static::createStub(CartSerializationCleaner::class), new CartCompressor(false, 'gzip'), 30);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clear the Redis storage only if it was set up and not skipped
        if (isset($this->redis)) {
            $this->redis->flushAll();
        }
    }

    public function testPersisting(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = static::createStub(ChannelContext::class);

        $this->persister->save($cart, $context);

        $loaded = $this->persister->load($token, $context);

        static::assertSame($cart->getToken(), $loaded->getToken());
        static::assertEquals($cart->getLineItems(), $loaded->getLineItems());

        $cart->getLineItems()->clear();

        $this->persister->save($cart, $context);

        $this->expectException(CartTokenNotFoundException::class);
        $this->persister->load($token, $context);
    }

    public function testDelete(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = static::createStub(ChannelContext::class);

        $this->persister->save($cart, $context);

        $this->persister->load($token, $context);

        $this->persister->delete($token, $context);

        $this->expectException(CartTokenNotFoundException::class);
        $this->persister->load($token, $context);
    }

    public function testSavingExistingCartDoesNotRecreateDeletedCart(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = static::createStub(ChannelContext::class);

        $this->persister->save($cart, $context);
        $this->persister->delete($token, $context);
        $this->persister->save($cart, $context);

        static::assertSame(0, $this->redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testLoadGzipCompressedCart(): void
    {
        $token = Uuid::randomHex();

        $cart = new Cart($token);
        $compressed = ['content' => gzcompress(serialize(['cart' => $cart, 'rule_ids' => []]), 9), 'compressed' => 1];

        $this->redis->set(RedisCartPersister::PREFIX . $token, serialize($compressed));

        $loaded = $this->persister->load($token, static::createStub(ChannelContext::class));

        $cart->setPersisted(true);

        static::assertEquals($cart, $loaded);
    }

    public function testLoadZstdCompressedCart(): void
    {
        if (!\function_exists('zstd_compress')) {
            static::markTestSkipped('zstd extension is not installed');
        }

        $token = Uuid::randomHex();

        $cart = new Cart($token);
        $compressed = ['content' => \zstd_compress(serialize(['cart' => $cart, 'rule_ids' => []]), 9), 'compressed' => 2];

        $this->redis->set(RedisCartPersister::PREFIX . $token, serialize($compressed));

        $loaded = $this->persister->load($token, static::createStub(ChannelContext::class));

        $cart->setPersisted(true);

        static::assertEquals($cart, $loaded);
    }
}
