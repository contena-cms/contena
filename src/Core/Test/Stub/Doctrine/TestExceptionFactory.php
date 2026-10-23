<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Doctrine;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class TestExceptionFactory
{
    public static function createException(string $message): Exception
    {
        return new class($message) extends \Exception implements Exception {
            public function __construct(string $message)
            {
                parent::__construct($message);
            }
        };
    }

    public static function createDriverException(string $message): Exception\DriverException
    {
        return new Exception\DriverException(
            new \Doctrine\DBAL\Driver\PDO\Exception($message),
            null,
        );
    }
}
