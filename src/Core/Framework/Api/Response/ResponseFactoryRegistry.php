<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Response;

use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ResponseFactoryRegistry
{
    private const string DEFAULT_RESPONSE_TYPE = 'application/vnd.api+json';

    /**
     * @var ResponseFactoryInterface[]
     */
    private readonly array $responseFactories;

    /**
     * @internal
     */
    public function __construct(ResponseFactoryInterface ...$responseFactories)
    {
        $this->responseFactories = $responseFactories;
    }

    public function getType(Request $request): ResponseFactoryInterface
    {
        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        $contentTypes = $request->getAcceptableContentTypes();
        if (\in_array('*/*', $contentTypes, true)) {
            $contentTypes[] = self::DEFAULT_RESPONSE_TYPE;
        }

        foreach ($contentTypes as $contentType) {
            foreach ($this->responseFactories as $factory) {
                if ($factory->supports($contentType, $context->getSource())) {
                    return $factory;
                }
            }
        }

        throw new UnsupportedMediaTypeHttpException(\sprintf('All provided media types are unsupported. (%s)', implode(', ', $contentTypes)));
    }
}
