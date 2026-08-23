<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Transport;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\Mail;
use Contena\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\Mail\Transport\MailerTransportDecorator;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailerTransportDecorator::class)]
class MailerTransportDecoratorTest extends TestCase
{
    private MockObject&TransportInterface $decorated;

    private MailAttachmentsBuilder $attachmentsBuilder;

    private Filesystem $filesystem;

    private MailerTransportDecorator $decorator;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(TransportInterface::class);
        $this->attachmentsBuilder = static::createStub(MailAttachmentsBuilder::class);
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->decorator = new MailerTransportDecorator(
            $this->decorated,
            $this->attachmentsBuilder,
            $this->filesystem,
        );
    }

    public function testMailerTransportDecoratorDefault(): void
    {
        $mail = static::createStub(Email::class);
        $envelope = static::createStub(Envelope::class);

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
    }

    public function testMailerTransportDecoratorWithUrlAttachments(): void
    {
        $mail = new Mail();
        $envelope = static::createStub(Envelope::class);
        $mail->addAttachmentUrl('foo');
        $mail->addAttachmentUrl('bar');

        $this->filesystem->write('foo', 'foo');
        $this->filesystem->write('bar', 'bar');

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());
    }

    public function testMailerTransportDecoratorWithBuildAttachments(): void
    {
        $mail = new Mail();
        $envelope = static::createStub(Envelope::class);
        $mailAttachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false, ['foo', 'bar']),
        );

        $mail->setMailAttachmentsConfig($mailAttachmentsConfig);

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $attachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $attachmentsBuilder
            ->expects($this->once())
            ->method('buildAttachments')
            ->with(
                $mailAttachmentsConfig->getContext(),
                $mailAttachmentsConfig->getMailTemplate(),
                $mailAttachmentsConfig->getExtension(),
            )
            ->willReturn([
                ['id' => 'foo', 'content' => 'foo', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
                ['id' => 'bar', 'content' => 'bar', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
            ]);

        $decorator = new MailerTransportDecorator(
            $this->decorated,
            $attachmentsBuilder,
            $this->filesystem,
        );

        $decorator->send($mail, $envelope);

        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());
    }
}
