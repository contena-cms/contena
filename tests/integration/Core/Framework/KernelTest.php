<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUTCIsAlwaysSetToDatabase(): void
    {
        $c = static::getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }
}
