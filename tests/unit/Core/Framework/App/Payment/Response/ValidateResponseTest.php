<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Payment\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Payment\Response\ValidateResponse;

/**
 * @internal
 */
#[CoversClass(ValidateResponse::class)]
class ValidateResponseTest extends TestCase
{
    public function testEmpty(): void
    {
        $response = new ValidateResponse();
        static::assertSame([], $response->getPreOrderPayment());
    }

    public function testNonEmpty(): void
    {
        $response = new ValidateResponse();
        $response->assign([
            'preOrderPayment' => [
                'foo' => 'bar',
            ],
        ]);
        static::assertSame(['foo' => 'bar'], $response->getPreOrderPayment());
    }
}
