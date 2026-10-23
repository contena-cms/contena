<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Payment\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Payment\Response\AbstractResponse;

/**
 * @internal
 */
#[CoversClass(AbstractResponse::class)]
class AbstractResponseTest extends TestCase
{
    public function testErrorMessageIsNullByDefault(): void
    {
        $response = $this->createResponse();

        static::assertNull($response->getErrorMessage());
    }

    public function testCreateAssignsData(): void
    {
        $response = $this->createResponse()::create([
            'message' => 'test message',
        ]);

        static::assertSame('test message', $response->getErrorMessage());
    }

}
