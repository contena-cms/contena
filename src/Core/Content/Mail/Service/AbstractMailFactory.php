<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @phpstan-type MailNameCombination array<string, string|null>
 * @phpstan-type Contents array<'text/plain'|'text/html', resource|string|null>
 * @phpstan-type BinAttachments array<int|string, array{content: resource|string, fileName: string|null, mimeType: string|null}>|null
 * @phpstan-type MailData array{
 *     attachmentsConfig?: MailAttachmentsConfig|null,
 *     recipientsCc?: string|array<string, string|null>,
 *     recipientsBcc?: string|array<string, string|null>,
 *     replyTo?: string|array<string, string|null>,
 *     returnPath?: string|array<string, string|null>,
 *     testMode?: bool,
 *     senderMail?: string,
 *     senderEmail?: string,
 *     senderName?: string|null,
 *     subject?: string|null,
 *     contentHtml?: string|null,
 *     contentPlain?: string|null,
 *     recipients?: MailNameCombination,
 *     binAttachments?: BinAttachments,
 *     mediaIds?: list<string>,
 *     attachments?: array<DataPart|mixed>,
 *     extensions?: array<string, mixed>,
 *     ...<string, mixed>,
 * }
 */
abstract class AbstractMailFactory
{
    /**
     * @param MailNameCombination $sender e.g. ['contena@example.com' => 'Contena']
     * @param MailNameCombination $recipients e.g. ['contena@example.com' => 'Contena', 'symfony@example.com' => 'Symfony']
     * @param Contents $contents e.g. ['text/plain' => 'Foo', 'text/html' => '<h1>Bar</h1>']
     * @param list<string> $attachments
     * @param MailData $additionalData e.g. ['recipientsCc' => ['contena@example.com' => 'contena', 'recipientsBcc' => 'contena@example.com', 'replyTo' => 'reply@example.com', 'returnPath' => 'bounce@example.com']
     * @param BinAttachments $binAttachments
     */
    abstract public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email;

    abstract public function getDecorated(): AbstractMailFactory;
}
