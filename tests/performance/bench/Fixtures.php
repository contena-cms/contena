<?php declare(strict_types=1);

namespace Contena\Tests\Bench;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Assert\Serialization;
use Contena\Core\Test\FixtureLoader;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal - only for performance benchmarks
 */
class Fixtures
{
    private static ?IdsCollection $ids = null;

    private static ?ChannelContext $context = null;

    public function load(string $data): void
    {
        $content = $data;
        if (is_file($data)) {
            $content = (string) \file_get_contents($data);
        }
        $container = KernelLifecycleManager::getKernel()->getContainer();
        $loader = new FixtureLoader($container);
        $ids = $loader->load($content, self::$ids);

        $sql = '
CREATE TABLE IF NOT EXISTS `php_bench` (
  `key` varchar(50) NOT NULL,
  `ids` longblob NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        $container
            ->get(Connection::class)
            ->executeStatement($sql);

        $sql = 'REPLACE INTO php_bench (`key`, `ids`) VALUES (:key, :ids)';

        $container
            ->get(Connection::class)
            ->executeStatement($sql, ['key' => 'ids', 'ids' => \serialize($ids)]);
    }

    public static function getIds(): IdsCollection
    {
        if (!self::$ids instanceof IdsCollection) {
            $ids = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(Connection::class)
                ->fetchOne('SELECT ids FROM php_bench WHERE `key` = :key', ['key' => 'ids']);

            self::$ids = Serialization::assertUnserializedInstanceOf(IdsCollection::class, $ids);
        }

        return self::$ids;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function context(array $options = []): ChannelContext
    {
        if (!self::$context instanceof ChannelContext) {
            self::$context = KernelLifecycleManager::getKernel()
                ->getContainer()
                ->get(ChannelContextFactory::class)
                ->create(Uuid::randomHex(), self::getIds()->get('sales-channel'), $options);
        }

        return self::$context;
    }
}
