<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

/**
 * @internal
 */
#[CoversClass(CategoryNonExistentExceptionHandler::class)]
class CategoryNonExistentExceptionHandlerTest extends TestCase
{
    public function testExceptionHandler(): void
    {
        $handler = new CategoryNonExistentExceptionHandler();

        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, $handler->getPriority());

        $afterException = new ForeignKeyConstraintViolationException(
            Exception::new(new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`contena`.`category`, CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL O)')),
            new Query('SOME QUERY', [], [])
        );

        $matched = $handler->matchException($afterException);

        static::assertInstanceOf(CategoryException::class, $matched);
        static::assertSame('Category to insert after not found.', $matched->getMessage());

        static::assertNull($handler->matchException(new \Exception('Some other exception')));
    }
}
