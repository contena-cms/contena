<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberBeforeLoginEvent;

/**
 * @internal
 */
#[CoversClass(MailStorer::class)]
class MailStorerTest extends TestCase
{
    private MailStorer $storer;

    protected function setUp(): void
    {
        $this->storer = new MailStorer();
    }

    public function testStoreWithAware(): void
    {
        $mail = new MailRecipientStruct(['foo@bar.com' => 'Foo Bar']);
        $mail->setBcc('bcc@bar.com');
        $mail->setCc('cc@bar.com');

        $stored = $this->storer->store(new MailEvent($mail), []);

        static::assertSame([
            'recipients' => ['foo@bar.com' => 'Foo Bar'],
            'bcc' => 'bcc@bar.com',
            'cc' => 'cc@bar.com',
        ], $stored[MailAware::MAIL_STRUCT]);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(FlowEventAware::class);

        $stored = $this->storer->store($event, []);

        static::assertArrayNotHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertArrayNotHasKey(MailAware::CHANNEL_ID, $stored);
    }

    public function testStoreDoesNotOverwriteExistingMailStruct(): void
    {
        $existing = ['recipients' => ['existing@example.com' => 'Existing']];
        $mail = new MailRecipientStruct(['new@example.com' => 'New']);

        $stored = $this->storer->store(new MailEvent($mail), [MailAware::MAIL_STRUCT => $existing]);

        static::assertSame($existing, $stored[MailAware::MAIL_STRUCT]);
    }

    public function testStoreIgnoresUnavailableMailRecipientsAndStoresChannelId(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('channel-id');
        $event = new MemberBeforeLoginEvent($context, 'member@example.com');

        $stored = $this->storer->store($event, []);

        static::assertArrayNotHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertSame('channel-id', $stored[MailAware::CHANNEL_ID]);
    }

    public function testRestoreHasStored(): void
    {
        $store = [
            MailAware::MAIL_STRUCT => [
                'recipients' => ['foo@bar.com' => 'Foo Bar'],
                'bcc' => 'bcc@bar.com',
                'cc' => 'cc@bar.com',
            ],
            MailAware::CHANNEL_ID => 'channel-id',
        ];

        $flow = new StorableFlow('test', Context::createDefaultContext(), $store);

        $this->storer->restore($flow);

        static::assertSame('channel-id', $flow->getData(MailAware::CHANNEL_ID));
        $mail = $flow->getData(MailAware::MAIL_STRUCT);
        static::assertInstanceOf(MailRecipientStruct::class, $mail);
        static::assertSame('Foo Bar', $mail->getRecipients()['foo@bar.com']);
        static::assertSame('bcc@bar.com', $mail->getBcc());
        static::assertSame('cc@bar.com', $mail->getCc());
    }
}

/**
 * @internal
 */
class MailEvent implements MailAware, FlowEventAware
{
    public function __construct(private readonly MailRecipientStruct $recipients)
    {
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getName(): string
    {
        return 'test.mail';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
