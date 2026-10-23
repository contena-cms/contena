<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service;

use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Request\PreviewRequest;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class MailTemplateService
{
    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly EntityRepository $mailTemplateRepository,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly MailDataProvider $mailDataProvider,
        private readonly MailDataSimulator $mailDataSimulator,
        private readonly MailTemplateContentBuilder $mailTemplateContentBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractTranslator $translator,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
    ) {
    }

    public function loadTemplate(string $templateId, Context $context): MailTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('mailTemplateType');
        $criteria->addAssociation('media.media');
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->getEntities()->first();

        if ($mailTemplate === null) {
            throw MailTemplateException::templateNotFound();
        }

        \assert($mailTemplate instanceof MailTemplateEntity);

        return $mailTemplate;
    }

    /**
     * @return array<int|string, MailTemplateRenderResult>
     */
    public function simulate(
        SimulateRequest $simulateRequest,
        Context $context,
    ): array {
        $renderedResult = [];

        $templateData = $this->mailDataSimulator->getTemplateData($simulateRequest->eventName, $context, $simulateRequest->channel);
        $templateData = $this->enrichTemplateDataForRendering($templateData, $context, $simulateRequest->channel);

        $channelId = $simulateRequest->channel?->getId();
        $injectTranslator = $channelId !== null && $this->translator->getSnippetSetId() === null;

        try {
            if ($injectTranslator) {
                $this->injectTranslator($context, $channelId);
            }

            if (!$simulateRequest->strictRendering) {
                $this->templateRenderer->enableTestMode();
            }

            foreach ($simulateRequest->templateParts as $key => $content) {
                try {
                    $rendered = $this->templateRenderer->render(
                        $content,
                        $templateData,
                        $context,
                        $this->shouldEscapeHtml($key),
                    );

                    $renderedResult[$key] = MailTemplateRenderResult::success($rendered);
                } catch (\Throwable $e) {
                    $renderedResult[$key] = MailTemplateRenderResult::errorFromThrowable($e);
                }
            }

            if (!$simulateRequest->strictRendering) {
                $this->templateRenderer->disableTestMode();
            }
        } finally {
            if ($injectTranslator) {
                $this->translator->resetInjection();
            }
        }

        return $renderedResult;
    }

    /**
     * @return array<int|string, MailTemplateRenderResult>
     */
    public function preview(
        PreviewRequest $request,
        Context $context,
    ): array {
        $renderedResult = [];

        $templateData = $this->mailDataProvider->getTemplateData(
            $request->mailTemplate,
            $request->entityMapping,
            $context,
            $request->templateData
        );
        $templateData = $this->enrichTemplateDataForRendering($templateData, $context, $request->channel);

        $channelId = $request->channel?->getId();
        $injectTranslator = $channelId !== null && $this->translator->getSnippetSetId() === null;

        try {
            if ($injectTranslator) {
                $this->injectTranslator($context, $channelId);
            }

            if (!$request->strictRendering) {
                $this->templateRenderer->enableTestMode();
            }

            $templateContent = $this->getTemplateContent($request->mailTemplate);

            if ($request->includeHeaderFooter) {
                $templateContent = array_replace(
                    $templateContent,
                    $this->mailTemplateContentBuilder->build([
                        'contentPlain' => $templateContent['contentPlain'],
                        'contentHtml' => $templateContent['contentHtml'],
                    ], $request->channel)
                );
            }

            foreach ($templateContent as $key => $value) {
                try {
                    $rendered = $this->templateRenderer->render(
                        $value,
                        $templateData,
                        $context,
                        $this->shouldEscapeHtml($key),
                    );

                    $renderedResult[$key] = MailTemplateRenderResult::success($rendered);
                } catch (\Throwable $e) {
                    $renderedResult[$key] = MailTemplateRenderResult::errorFromThrowable($e);
                }
            }

            if (!$request->strictRendering) {
                $this->templateRenderer->disableTestMode();
            }
        } finally {
            if ($injectTranslator) {
                $this->translator->resetInjection();
            }
        }

        return $renderedResult;
    }

    /**
     * @return array<string,int|string|bool>[]
     */
    public function getAvailableVariables(
        string $flowEvent,
        Context $context,
        ?string $parentVariablePath = '',
    ): array {
        $templateData = $this->mailDataSimulator->getTemplateData($flowEvent, $context);

        if ($parentVariablePath === null || $parentVariablePath === '') {
            return \array_map(
                fn ($fieldName) => [
                    'fieldName' => $fieldName,
                    'hasChildren' => \is_object($templateData[$fieldName])
                        || (\is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []),
                ],
                \array_keys($templateData)
            );
        }

        $fieldPathParts = \explode('.', $parentVariablePath);

        foreach ($fieldPathParts as $fieldPathPart) {
            if ($templateData instanceof Collection) {
                if ($fieldPathPart !== 'first') {
                    return [];
                }

                $templateData = $templateData->first();

                continue;
            }

            if ($templateData instanceof Struct) {
                $templateData = $templateData->jsonSerialize();
            }

            if (!\is_array($templateData) || !\array_key_exists($fieldPathPart, $templateData)) {
                return [];
            }

            $templateData = $templateData[$fieldPathPart];
        }

        if ($templateData instanceof Collection) {
            $first = $templateData->first();

            if ($first === null) {
                return [];
            }

            return [[
                'fieldName' => 'first',
                'hasChildren' => \is_object($first) || (\is_array($first) && $first !== []),
            ]];
        }

        if ($templateData instanceof Struct) {
            $templateData = $templateData->jsonSerialize();
        }

        if (!\is_array($templateData)) {
            return [];
        }

        return \array_map(
            fn ($fieldName) => [
                'fieldName' => $fieldName,
                'hasChildren' => \is_object($templateData[$fieldName])
                    || (\is_array($templateData[$fieldName]) && $templateData[$fieldName] !== []),
            ],
            \array_keys($templateData)
        );
    }

    /**
     * @return array{
     *     subject: string,
     *     senderName: string,
     *     contentHtml: string,
     *     contentPlain: string
     * }
     */
    private function getTemplateContent(MailTemplateEntity $mailTemplate): array
    {
        return [
            'subject' => $mailTemplate->getSubject() ?? '',
            'senderName' => $mailTemplate->getSenderName() ?? '',
            'contentHtml' => $mailTemplate->getContentHtml() ?? '',
            'contentPlain' => $mailTemplate->getContentPlain() ?? '',
        ];
    }

    private function injectTranslator(Context $context, string $channelId): void
    {
        $this->translator->injectSettings(
            $channelId,
            $context->getLanguageId(),
            $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId()),
            $context
        );
    }

    private function shouldEscapeHtml(string $templatePart): bool
    {
        return \in_array($templatePart, ['contentHtml', 'headerHtml', 'footerHtml'], true);
    }

    /**
     * @param array<string, mixed> $templateData
     *
     * @return array<string, mixed>
     */
    private function enrichTemplateDataForRendering(array $templateData, Context $context, ?ChannelEntity $channel): array
    {
        $templateChannel = $templateData['channel'] ?? null;
        if (!$channel instanceof ChannelEntity && $templateChannel instanceof ChannelEntity) {
            $channel = $templateChannel;
        }

        if ($channel instanceof ChannelEntity) {
            $templateData['channel'] ??= $channel;
            $templateData['channelId'] ??= $channel->getId();
        }

        $event = new MailTemplateRenderContextEvent($templateData, $context, $channel);
        $this->eventDispatcher->dispatch($event);

        return $event->getTemplateData();
    }
}
