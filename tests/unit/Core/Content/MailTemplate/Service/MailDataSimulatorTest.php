<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\MailDataSimulator;
use Contena\Core\Framework\Context;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(MailDataSimulator::class)]
class MailDataSimulatorTest extends TestCase
{
    public function testItProvidesUserRecoveryPreviewData(): void
    {
        $data = new MailDataSimulator()->getTemplateData(
            UserRecoveryRequestEvent::EVENT_NAME,
            Context::createDefaultContext(),
        );

        static::assertSame(['userRecovery', 'resetUrl'], \array_keys($data));
        static::assertInstanceOf(UserRecoveryEntity::class, $data['userRecovery']);
        static::assertInstanceOf(UserEntity::class, $data['userRecovery']->getUser());
        static::assertSame('admin@example.com', $data['userRecovery']->getUser()->getEmail());
        static::assertSame(
            'https://example.com/admin/recovery/preview-recovery-hash',
            $data['resetUrl'],
        );
    }

    public function testItReturnsNoPreviewDataForUnknownEvents(): void
    {
        static::assertSame(
            [],
            new MailDataSimulator()->getTemplateData('unknown.event', Context::createDefaultContext()),
        );
    }
}
