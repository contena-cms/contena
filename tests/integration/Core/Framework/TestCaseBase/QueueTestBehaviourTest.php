<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\TestCaseBase;

use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueueTestBehaviourTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    public function testNoAssertionIsPerformedInTheTrait(): void
    {
        // Ensures runWorker() and getDispatchedMessageCount() do not perform any PHPUnit assertions,
        // and clearQueue() is implicitly verified via its #[Before]/#[After] hooks around this test.
        static::expectNotToPerformAssertions();

        $this->runWorker();
        $this->getDispatchedMessageCount(\stdClass::class);
    }
}
