<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Request\Resolver;

use Contena\Core\Content\MailTemplate\Request\PreviewRequest;
use Contena\Core\Content\MailTemplate\Service\MailTemplateService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
readonly class PreviewRequestResolver extends AbstractMailTemplateRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private MailTemplateService $mailTemplateService,
    ) {
    }

    /**
     * @return \Generator<PreviewRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== PreviewRequest::class) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        yield $this->make(new RequestDataBag($request->request->all()), $context);
    }

    private function make(RequestDataBag $request, Context $context): PreviewRequest
    {
        $templateId = $request->getString('mailTemplateId');
        $mailTemplate = $this->mailTemplateService->loadTemplate($templateId, $context);

        $entities = $this->normalizeArrayParameter('entities', $request->get('entities', []));
        $entities = $this->filterAvailableEntities($entities, $mailTemplate);

        $templateData = $this->normalizeArrayParameter('templateData', $request->get('templateData', []));

        $includeHeaderFooter = $this->normalizeBoolParameter('includeHeaderFooter', $request->get('includeHeaderFooter', false));
        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', false));

        return new PreviewRequest(
            $mailTemplate,
            $entities,
            $templateData,
            $includeHeaderFooter,
            $strictRendering,
        );
    }
}
