<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Request\Resolver;

use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
readonly class SimulateRequestResolver extends AbstractMailTemplateRequestResolver implements ValueResolverInterface
{
    public function __construct(
        private ChannelProvider $channelProvider,
    ) {
    }

    /**
     * @return \Generator<SimulateRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== SimulateRequest::class) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        yield $this->make(new RequestDataBag($request->request->all()), $context);
    }

    private function make(RequestDataBag $request, Context $context): SimulateRequest
    {
        $templateParts = $this->normalizeArrayParameter('templateParts', $request->get('templateParts'));

        $eventName = $this->normalizeStringParameter('eventName', $request->get('eventName'));
        if ($eventName === null) {
            throw MailTemplateException::invalidRequestParameterType('eventName', 'string', get_debug_type($eventName));
        }

        $channel = null;
        $channelId = $this->normalizeStringParameter('channelId', $request->get('channelId'));
        if ($channelId !== null) {
            $channel = $this->channelProvider->getData($channelId, $context);
            if ($channel === null) {
                throw MailTemplateException::invalidChannelId($channelId);
            }
        }

        $strictRendering = $this->normalizeBoolParameter('strictRendering', $request->get('strictRendering', true));

        return new SimulateRequest($templateParts, $eventName, $channel, $strictRendering);
    }
}
