<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service;

use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\Mail\Service\AbstractMailService;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
class MailTemplateSendService
{
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly MailDataProvider $mailDataProvider,
    ) {
    }

    public function getTemplateDataAndSend(
        GetDataAndSendRequest $request,
        Context $context,
    ): ?Email {
        $templateData = $this->mailDataProvider->getTemplateData(
            $request->mailTemplate,
            $request->entityMapping,
            $context,
            $request->templateData
        );

        return $this->send($request->mailPayload, $context, $templateData, $request->mailTemplate);
    }

    /**
     * @param array<string|int,mixed> $templateData
     */
    public function send(
        MailPayload $mailPayload,
        Context $context,
        array $templateData,
        ?MailTemplateEntity $mailTemplate = null,
    ): ?Email {
        $data = $mailPayload->toArray();

        $extension = new MailSendSubscriberConfig(
            false,
            $mailPayload->documentIds,
            $mailPayload->mediaIds,
        );

        $orderId = null;
        if (\array_key_exists('order', $templateData)) {
            if (\is_array($templateData['order'])) {
                $orderId = $templateData['order']['id'] ?? null;
            } elseif ($templateData['order'] instanceof OrderEntity) {
                $orderId = $templateData['order']->getId();
            }
        }

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            $mailTemplate ?? new MailTemplateEntity(),
            $extension,
            [],
            $orderId,
        );

        return $this->mailService->send($data, $context, $templateData);
    }
}
