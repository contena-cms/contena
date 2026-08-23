<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Media\Event\MediaUploadedEvent;
use Contena\Core\Framework\Event\BusinessEventRegistry;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 */
#[CoversClass(BusinessEventRegistry::class)]
class BusinessEventRegistryTest extends TestCase
{
    public function testRegistersRetainedPlatformFlowEvents(): void
    {
        static::assertSame([
            MailBeforeSentEvent::class,
            MailBeforeValidateEvent::class,
            MailSentEvent::class,
            MediaUploadedEvent::class,
            UserRecoveryRequestEvent::class,
        ], new BusinessEventRegistry()->getClasses());
    }
}
