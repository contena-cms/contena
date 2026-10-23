<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso\SsoUser;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class SsoUserInvitationMailServiceTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private string $localeId;

    private SsoUserInvitationMailService $ssoUserInvitationMailService;

    protected function setUp(): void
    {
        parent::setUp();

        $localeRepository = static::getContainer()->get('locale.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', 'pl-PL'));
        $locale = $localeRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(LocaleEntity::class, $locale);

        $this->localeId = $locale->getId();

        $this->getContainer()->get('language.repository')->create(
            [[
                'id' => Uuid::randomHex(),
                'name' => 'pl-PL',
                'localeId' => $this->localeId,
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'active' => true,
                'channels' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
                'channelDefaultAssignments' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
            ]],
            Context::createDefaultContext()
        );

        $this->ssoUserInvitationMailService = static::getContainer()->get(SsoUserInvitationMailService::class);
    }

    public function testSendInvitationMailToUser(): void
    {
        $source = new AdminApiSource(null, null);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            MailBeforeSentEvent::class,
            static function (MailBeforeSentEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->ssoUserInvitationMailService->sendInvitationMailToUser(
            'foo@bar.baz',
            $this->localeId,
            $context
        );

        static::assertInstanceOf(MailBeforeSentEvent::class, $caughtEvent);
        static::assertSame('Administrator invited you to join Demostore', $caughtEvent->getData()['subject']);
        static::assertNull($caughtEvent->getData()['senderEmail']);
    }
}
