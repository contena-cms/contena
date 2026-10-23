<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Monolog\Level;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-import-type MailData from AbstractMailFactory
 * @phpstan-import-type MailNameCombination from AbstractMailFactory
 * @phpstan-import-type BinAttachments from AbstractMailFactory
 *
 * @phpstan-type ValidatedMailData array{
 *     attachmentsConfig?: MailAttachmentsConfig|null,
 *     recipientsCc?: string|array<string, string|null>,
 *     recipientsBcc?: string|array<string, string|null>,
 *     replyTo?: string|array<string, string|null>,
 *     returnPath?: string|array<string, string|null>,
 *     testMode?: bool,
 *     channelId?: string,
 *     senderMail?: string,
 *     senderEmail?: string,
 *     senderName?: string|null,
 *     subject: string,
 *     contentHtml: non-empty-string,
 *     contentPlain: non-empty-string,
 *     recipients: MailNameCombination,
 *     binAttachments?: BinAttachments,
 *     mediaIds?: list<string>,
 *     attachments?: list<DataPart|mixed>,
 *     documentIds?: list<string>,
 *     extensions?: array<string, mixed>,
 *     ...<string, mixed>,
 * }
 */
class MailService extends AbstractMailService
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly AbstractMailFactory $mailFactory,
        private readonly AbstractMailSender $mailSender,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $channelRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly MailTemplateContentBuilder $mailTemplateContentBuilder,
        private readonly MailMetricsInstrumentor $mailMetrics,
        private readonly AbstractTranslator $translator,
    ) {
    }

    public function getDecorated(): AbstractMailService
    {
        throw new DecorationPatternException(self::class);
    }

    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $beforeValidateEvent = new MailBeforeValidateEvent($data, $context, $templateData);
        $this->eventDispatcher->dispatch($beforeValidateEvent);
        if ($beforeValidateEvent->isPropagationStopped()) {
            return null;
        }

        $data = $beforeValidateEvent->getData();
        $templateData = $beforeValidateEvent->getTemplateData();

        $this->dataValidator->validate($data, $this->getValidationDefinition($context));

        // existence and values validated in step before
        \assert(\array_key_exists('recipients', $data) && \is_array($data['recipients']) && $data['recipients'] !== []);
        \assert(\array_key_exists('contentHtml', $data) && \is_string($data['contentHtml']) && $data['contentHtml'] !== '');
        \assert(\array_key_exists('contentPlain', $data) && \is_string($data['contentPlain']) && $data['contentPlain'] !== '');
        \assert(\array_key_exists('subject', $data) && \is_string($data['subject']) && $data['subject'] !== '');

        $channel = $this->getChannel($data, $templateData, $context);
        $injectTranslator = $channel !== null && $this->translator->getSnippetSetId() === null;

        try {
            if ($injectTranslator) {
                $this->translator->injectSettings(
                    $channel->getId(),
                    $context->getLanguageId(),
                    $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId()),
                    $context
                );
            }

            $mail = $this->createMail($data, $templateData, $context, $channel);
        } finally {
            if ($injectTranslator) {
                $this->translator->resetInjection();
            }
        }

        if ($mail === null) {
            return null;
        }

        if (trim($mail->getBody()->toString()) === '') {
            $this->mailError('Mail body is null', $context, $templateData, data: $data, mail: $mail);

            return null;
        }

        if (isset($data['attachments']) && \is_array($data['attachments'])) {
            foreach ($data['attachments'] as $attachment) {
                if (!$attachment instanceof DataPart) {
                    $this->mailError(
                        errorMessage: 'Invalid attachment to mail provided, skipping this attachment',
                        context: $context,
                        templateData: $templateData,
                        level: Level::Warning,
                        data: $data,
                        mail: $mail,
                    );

                    continue;
                }

                $mail->addPart($attachment);
            }
        }

        $beforeSentEvent = new MailBeforeSentEvent($data, $mail, $context, $templateData['eventName'] ?? null);
        $this->eventDispatcher->dispatch($beforeSentEvent);
        if ($beforeSentEvent->isPropagationStopped()) {
            return null;
        }

        try {
            $this->sendMail($mail, $templateData);
        } catch (\Throwable $exception) {
            $this->mailError(
                errorMessage: \sprintf('Could not send mail with error message: %s', $exception->getMessage()),
                context: $context,
                templateData: $templateData,
                template: (string) $mail->getHtmlBody(),
                exception: $exception,
                data: $data,
                mail: $mail,
            );

            return null;
        }

        $this->eventDispatcher->dispatch(new MailSentEvent(
            $data['subject'],
            $data['recipients'],
            ['text/html' => $mail->getHtmlBody(), 'text/plain' => $mail->getTextBody()],
            $context,
            $templateData['eventName'] ?? null,
            $data,
            $templateData,
            $mail,
        ));

        return $mail;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function sendMail(Email $mail, array $templateData): void
    {
        $eventName = $templateData['eventName'] ?? null;

        $this->mailMetrics->measureSend(
            \is_string($eventName) ? $eventName : null,
            fn () => $this->mailSender->send($mail),
        );
    }

    private function getValidationDefinition(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('recipients', new NotBlank(), new Type('array'));
        $definition->add('channelId', new EntityExists(entity: ChannelDefinition::ENTITY_NAME, context: $context));
        $definition->add('contentHtml', new NotBlank(), new Type('string'));
        $definition->add('contentPlain', new NotBlank(), new Type('string'));
        $definition->add('subject', new NotBlank(), new Type('string'));

        return $definition;
    }

    /**
     * @param ValidatedMailData $data
     * @param array<string, mixed> $templateData
     */
    private function createMail(array &$data, array $templateData, Context $context, ?ChannelEntity $channel): ?Email
    {
        $testMode = $this->isTestMode($data);

        $templateData['channel'] = $channel;
        $templateData['channelId'] = $channel?->getId();

        $renderContextEvent = new MailTemplateRenderContextEvent($templateData, $context, $channel);
        $this->eventDispatcher->dispatch($renderContextEvent);
        $templateData = $renderContextEvent->getTemplateData();

        $senderEmail = $this->getSender($data, $channel?->getId());
        if ($senderEmail === '') {
            $this->mailError(
                \sprintf(
                    'senderMail not configured for channel: %s. Please check system_config \'core.basicInformation.email\'',
                    (string) $channel?->getId(),
                ),
                $context,
                $templateData,
                data: $data,
            );
        }

        if ($testMode) {
            $this->templateRenderer->enableTestMode();
            if (\is_array($templateData['order'] ?? [])
                && (!isset($templateData['order']['deepLinkCode']) || !\is_string($templateData['order']['deepLinkCode']) || $templateData['order']['deepLinkCode'] === '')
            ) {
                $templateData['order']['deepLinkCode'] = 'home';
            }
        }
        $mailOptions = ['subject'];
        if (\is_string($data['senderName'] ?? null)) {
            $mailOptions[] = 'senderName';
        }
        foreach ($mailOptions as $renderDataIndex) {
            try {
                $data[$renderDataIndex] = $this->templateRenderer->render($data[$renderDataIndex] ?? '', $templateData, $context, false);
            } catch (\Throwable $e) {
                $this->mailError(
                    \sprintf(
                        'Could not render Mail-%s with error message: %s',
                        ucfirst($renderDataIndex),
                        $e->getMessage(),
                    ),
                    $context,
                    $templateData,
                    $data[$renderDataIndex] ?? null,
                    $e,
                    Level::Warning,
                    $data,
                );

                return null;
            }
        }

        $contents = [];
        foreach ($this->buildContents($data['contentPlain'], $data['contentHtml'], $channel) as $index => $template) {
            try {
                $contents[$index] = $this->templateRenderer->render($template, $templateData, $context, $index !== 'text/plain');
            } catch (\Throwable $e) {
                $this->mailError(
                    \sprintf('Could not render Mail-Content (%s) with error message: %s', $index, $e->getMessage()),
                    $context,
                    $templateData,
                    $template,
                    $e,
                    Level::Warning,
                    $data,
                );

                return null;
            }
        }

        if ($testMode) {
            $this->templateRenderer->disableTestMode();
        }

        $mail = $this->mailFactory->create(
            $data['subject'],
            [$senderEmail => $data['senderName'] ?? null],
            $data['recipients'],
            $contents,
            $this->getMediaUrls($data, $context),
            $data,
            $data['binAttachments'] ?? null
        );

        $mail->getHeaders()->addTextHeader(
            'Content-Language',
            $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId())
        );

        if ($testMode) {
            $headers = $mail->getHeaders();
            $headers->addTextHeader('X-Contena-Language-Id', $context->getLanguageId());

            $eventName = $templateData['eventName'] ?? '';
            if (\is_string($eventName) && $eventName !== '') {
                $headers->addTextHeader('X-Contena-Event-Name', $eventName);
            }

            if ($channel instanceof ChannelEntity) {
                $headers->addTextHeader('X-Contena-Sales-Channel-Id', $channel->getId());
            }
        }

        return $mail;
    }

    /**
     * @param ValidatedMailData $data
     */
    private function isTestMode(array $data): bool
    {
        return $this->systemConfigService->getBool(SetupStagingEvent::CONFIG_FLAG) || ($data['testMode'] ?? false);
    }

    /**
     * @param array<string, mixed> $templateData
     * @param array<string, mixed>|null $data
     */
    private function mailError(
        string $errorMessage,
        Context $context,
        array $templateData,
        ?string $template = null,
        ?\Throwable $exception = null,
        Level $level = Level::Error,
        ?array $data = null,
        ?Email $mail = null,
    ): void {
        $this->eventDispatcher->dispatch(
            new MailErrorEvent($context, $level, $exception, $errorMessage, $template, $templateData, $data, $mail)
        );

        $this->logger->log($level, $errorMessage, array_merge([
            'template' => $template,
            'exception' => (string) $exception,
        ], $templateData));
    }

    /**
     * @param ValidatedMailData $data
     */
    private function getSender(array $data, ?string $channelId): string
    {
        $senderEmail = $data['senderMail'] ?? $data['senderEmail'] ?? null;
        if (\is_string($senderEmail) && trim($senderEmail) !== '') {
            return trim($senderEmail);
        }

        return trim(
            $this->systemConfigService->getString(
                'core.basicInformation.email',
                $channelId
            )
        ) ?: trim(
            $this->systemConfigService->getString(
                'core.mailerSettings.senderAddress',
                $channelId
            )
        );
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @return array{'text/plain': string, 'text/html': string} e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     */
    private function buildContents(string $contentPlain, string $contentHtml, ?ChannelEntity $channel): array
    {
        $content = $this->mailTemplateContentBuilder->build([
            'contentPlain' => $contentPlain,
            'contentHtml' => $contentHtml,
        ], $channel);

        return [
            'text/plain' => $content['contentPlain'],
            'text/html' => $content['contentHtml'],
        ];
    }

    /**
     * @param MailData $data
     *
     * @return list<string>
     */
    private function getMediaUrls(array $data, Context $context): array
    {
        $mediaIds = $data['mediaIds'] ?? [];
        if ($mediaIds === []) {
            return [];
        }
        $criteria = new Criteria($mediaIds);
        $criteria->setTitle('mail-service::resolve-media-ids');
        $media = new MediaCollection();
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($criteria, &$media): void {
            $media = $this->mediaRepository->search($criteria, $context)->getEntities();
        });

        $urls = [];
        foreach ($media as $mediaItem) {
            $urls[] = $mediaItem->getPath();
        }

        return $urls;
    }

    /**
     * @param ValidatedMailData $data
     * @param array<string, mixed> $templateData
     */
    private function getChannel(array $data, array $templateData, Context $context): ?ChannelEntity
    {
        $channel = $templateData['channel'] ?? null;
        if ($channel instanceof ChannelEntity) {
            return $channel;
        }

        $channelId = $data['channelId'] ?? null;
        if (\is_string($channelId)) {
            $criteria = new Criteria([$channelId]);
            $criteria->setTitle('mail-service::resolve-sales-channel-domain');
            $criteria->addAssociation('mailHeaderFooter');
            $criteria->getAssociation('domains')
                ->addFilter(
                    new EqualsFilter('languageId', $context->getLanguageId())
                );

            // Should never be null, since we check in the validation that if a channelId is present it is valid...
            return $this->channelRepository->search(
                $criteria,
                $context
            )->getEntities()->first();
        }

        return null;
    }
}
