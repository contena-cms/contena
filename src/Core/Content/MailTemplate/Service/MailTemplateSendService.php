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
     * @param array<string,mixed> $templateData
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
            $mailPayload->mediaIds,
        );

        $data['attachmentsConfig'] = new MailAttachmentsConfig(
            $context,
            $mailTemplate ?? new MailTemplateEntity(),
            $extension,
        );

        return $this->mailService->send($data, $context, $templateData);
    }
}
