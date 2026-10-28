<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\MailSender;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\Maintenance\Staging\Handler\StagingMailHandler;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingMailHandler::class)]
class StagingMailHandlerTest extends TestCase
{
    public function testDisabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            []
        ));

        static::assertNull($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }

    public function testEnabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            true,
            []
        ));

        static::assertTrue($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }
}
