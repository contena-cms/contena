<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Contena\Core\Framework\Log\Package;

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
            'order_line_item' => [['id' => 'a'], ['id' => 'b']],
            'blog_review' => [['id' => 'c']],
        ]);

        $exception = new RestrictDeleteViolationException(new BlogDefinition(), [$violation]);

        static::assertSame(
            'The delete request for blog was denied due to a conflict. The entity is currently in use by: order_line_item (2), blog_review (1)',
            $exception->getMessage()
        );
        static::assertSame([$violation], $exception->getRestrictions());
        static::assertSame(
            [
                ['entityName' => 'order_line_item', 'count' => 2],
                ['entityName' => 'blog_review', 'count' => 1],
            ],
            $exception->getParameter('usages')
        );
    }
}
