<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Hasher;

/**
 * @internal
 *
 * @phpstan-type MailAttachments array<int, array{id?: string, content: string, fileName: string, mimeType: string|null}>
 */
class MailAttachmentsBuilder
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    /**
     * @return MailAttachments
     */
    public function buildAttachments(
        Context $context,
        MailTemplateEntity $mailTemplate,
        MailSendSubscriberConfig $extensions,
    ): array {
        $attachments = [];

        foreach ($mailTemplate->getMedia() ?? [] as $mailTemplateMedia) {
            if ($mailTemplateMedia->getMedia() === null || $mailTemplateMedia->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }

            $attachments[] = $this->mediaService->getAttachment($mailTemplateMedia->getMedia(), $context);
        }

        if ($extensions->getMediaIds() === []) {
            return $this->deduplicateAttachments($attachments);
        }

        $criteria = new Criteria($extensions->getMediaIds());
        $criteria->setTitle('send-mail::load-media');

        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();
        foreach ($entities as $media) {
            $attachments[] = $this->mediaService->getAttachment($media, $context);
        }

        return $this->deduplicateAttachments($attachments);
    }

    /**
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function deduplicateAttachments(array $attachments): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($attachments as $attachment) {
            $key = $attachment['id'] ?? Hasher::hash(
                json_encode([
                    $attachment['fileName'],
                    $attachment['mimeType'] ?? '',
                    Hasher::hash($attachment['content'], 'sha1'),
                ], \JSON_THROW_ON_ERROR),
                'sha1'
            );

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $attachment;
        }

        return $deduplicated;
    }
}
