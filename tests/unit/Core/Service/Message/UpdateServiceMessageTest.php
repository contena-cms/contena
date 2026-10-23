<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Message\UpdateServiceMessage;

/**
 * @internal
 */
#[CoversClass(UpdateServiceMessage::class)]
class UpdateServiceMessageTest extends TestCase
{
    public function testMeta(): void
    {
        $message = new UpdateServiceMessage('MyCoolService');

        static::assertSame('MyCoolService', $message->name);
    }
}
