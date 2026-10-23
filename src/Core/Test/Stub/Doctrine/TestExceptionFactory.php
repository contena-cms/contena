<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Doctrine;


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

}
