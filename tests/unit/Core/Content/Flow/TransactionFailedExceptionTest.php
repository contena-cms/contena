<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\TransactionFailedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(TransactionFailedException::class)]
class TransactionFailedExceptionTest extends TestCase
{
    public function testTransactionCommitFailed(): void
    {
        $previous = new \Exception('broken');
        $e = TransactionFailedException::because($previous);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(TransactionFailedException::TRANSACTION_FAILED, $e->getErrorCode());
        static::assertSame('Transaction failed because an exception occurred. Exception: broken', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }
}
