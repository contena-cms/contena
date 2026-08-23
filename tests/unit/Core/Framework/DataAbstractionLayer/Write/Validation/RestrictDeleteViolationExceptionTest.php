<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;

/**
 * @internal
 */
#[CoversClass(RestrictDeleteViolationException::class)]
class RestrictDeleteViolationExceptionTest extends TestCase
{
    #[TestDox('the message lists each restricting entity with its usage count')]
    public function testMessageListsUsages(): void
    {
        $violation = new RestrictDeleteViolation([
            'media' => [['id' => 'a'], ['id' => 'b']],
            'user' => [['id' => 'c']],
        ]);

        $exception = new RestrictDeleteViolationException(new DateDefinition(), [$violation]);

        static::assertSame(
            'The delete request for _date_field_test was denied due to a conflict. The entity is currently in use by: media (2), user (1)',
            $exception->getMessage()
        );
        static::assertSame([$violation], $exception->getRestrictions());
        static::assertSame(
            [
                ['entityName' => 'media', 'count' => 2],
                ['entityName' => 'user', 'count' => 1],
            ],
            $exception->getParameter('usages')
        );
    }
}
