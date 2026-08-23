<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Transport;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToRetrieveMetadata;
use Contena\Core\Content\Mail\Service\Mail;
use Contena\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
class MailerTransportDecorator implements \Stringable, TransportInterface
{
    public function __construct(
        private readonly TransportInterface $decorated,
        private readonly MailAttachmentsBuilder $attachmentsBuilder,
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    public function __toString(): string
    {
        return $this->decorated->__toString();
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (!$message instanceof Mail) {
            return $this->decorated->send($message, $envelope);
        }

        foreach ($message->getAttachmentUrls() as $url) {
            try {
                $mimeType = $this->filesystem->mimeType($url);
            } catch (UnableToRetrieveMetadata) {
                $mimeType = null;
            }
            $message->attach($this->filesystem->read($url) ?: '', basename($url), $mimeType);
        }

        $config = $message->getMailAttachmentsConfig();
        if ($config === null) {
            return $this->decorated->send($message, $envelope);
        }

        $attachments = $this->attachmentsBuilder->buildAttachments(
            $config->getContext(),
            $config->getMailTemplate(),
            $config->getExtension(),
        );

        foreach ($attachments as $attachment) {
            $message->attach($attachment['content'], $attachment['fileName'], $attachment['mimeType']);
        }

        return $this->decorated->send($message, $envelope);
    }
}
