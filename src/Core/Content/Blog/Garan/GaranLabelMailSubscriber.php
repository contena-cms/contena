<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Garan;

use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Passes the GARAN labels of an order's blogs to mail templates as `garanLabels`, attaches their guarantee terms
 * documents, and attaches the label PNGs the rendered mail references as inline parts.
 * Symfony Mime links each `cid:<name>` reference to the part with that name when the mail is sent.
 *
 * @internal
 */
class GaranLabelMailSubscriber implements EventSubscriberInterface
{
    private const TEMPLATE_DATA_KEY = 'garanLabels';

    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly GaranLabelInlineImage $inlineImage,
        private readonly EntityRepository $blogRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'addLabels',
            MailBeforeSentEvent::class => 'embedLabelImages',
        ];
    }

    public function addLabels(MailBeforeValidateEvent $event): void
    {
        $data = $event->getData();
        $template = ($data['contentHtml'] ?? '') . ($data['contentPlain'] ?? '');

        if (!str_contains($template, self::TEMPLATE_DATA_KEY)) {
            return;
        }

        $order = $event->getTemplateData()['order'] ?? null;

        if (!$order instanceof OrderEntity) {
            return;
        }

        $blogIds = array_values(array_unique(
            $order->getLineItems()?->fmap(static fn (OrderLineItemEntity $lineItem): ?string => $lineItem->getBlogId()) ?? []
        ));

        // empty criteria would load every blog
        if ($blogIds === []) {
            return;
        }

        $criteria = new Criteria($blogIds);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('guaranteeTermsMedia');

        // variants inherit the guarantee and manufacturer, admin-triggered mails do not consider inheritance by default
        $blogs = $event->getContext()->enableInheritance(
            fn (Context $context): BlogCollection => $this->blogRepository->search($criteria, $context)->getEntities()
        );

        $labels = [];
        $termsMediaIds = [];

        foreach ($blogs as $blog) {
            $duration = $this->resolver->resolveDuration($blog);

            if ($duration === null) {
                continue;
            }

            // `cid` is null for durations without an image, so the template falls back to the duration text
            $name = $this->inlineImage->getName((int) $blog->getGuaranteeMonths());
            $termsMedia = $blog->getGuaranteeTermsMedia();

            $labels[$blog->getId()] = [
                'cid' => $name !== null ? 'cid:' . $name : null,
                'duration' => $duration,
                // private media has an empty url
                'termsUrl' => $blog->getGuaranteeTermsUrl() ?: ($termsMedia?->getUrl() ?: null),
            ];

            // attaching a media without a file fails the whole mail on send
            if ($termsMedia !== null && $termsMedia->hasFile()) {
                $termsMediaIds[] = $termsMedia->getId();
            }
        }

        $event->addTemplateData(self::TEMPLATE_DATA_KEY, $labels);

        $attachmentsConfig = $data['attachmentsConfig'] ?? null;

        // § 479 (2) BGB requires the terms on a durable medium
        if ($termsMediaIds !== [] && $attachmentsConfig instanceof MailAttachmentsConfig) {
            $extension = $attachmentsConfig->getExtension();
            $extension->setMediaIds(array_values(array_unique([...$extension->getMediaIds(), ...$termsMediaIds])));
        }
    }

    public function embedLabelImages(MailBeforeSentEvent $event): void
    {
        $message = $event->getMessage();
        $html = $message->getHtmlBody();

        if (!\is_string($html)) {
            return;
        }

        $attachedNames = array_map(
            static fn (DataPart $part): ?string => $part->getName(),
            $message->getAttachments(),
        );

        foreach ($this->inlineImage->findReferencedNames($html) as $name) {
            if (\in_array($name, $attachedNames, true)) {
                continue;
            }

            $png = $this->inlineImage->render($name);

            if ($png === null) {
                continue;
            }

            $message->embed($png, $name, 'image/png');
        }
    }
}
