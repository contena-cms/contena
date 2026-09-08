<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Hmac\Guzzle;

use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Hmac\RequestSigner;
use Contena\Core\Framework\Context;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware
{
    final public const APP_REQUEST_TYPE = 'request_type';

    final public const APP_SECRET = 'app_secret';

    final public const VALIDATED_RESPONSE = 'validated_response';

    final public const APP_REQUEST_CONTEXT = 'app_request_context';

    final public const CONTENA_CONTEXT_LANGUAGE = 'ct-context-language';

    final public const CONTENA_USER_LANGUAGE = 'ct-user-language';

    /**
     * @internal
     */
    public function __construct(
        private readonly string $contenaVersion,
        private readonly AppLocaleProvider $localeProvider
    ) {
    }

    /**
     * @param callable(RequestInterface, array<mixed>): mixed $handler
     */
    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $this->getDefaultHeaderRequest($request, $options);

            if (!isset($options[self::APP_REQUEST_TYPE])) {
                return $handler($request, $options);
            }

            if (!\is_array($options[self::APP_REQUEST_TYPE])) {
                throw new InvalidArgumentException('request_type must be array');
            }

            $optionsRequestType = $options[self::APP_REQUEST_TYPE];

            if (!isset($optionsRequestType[self::APP_SECRET])) {
                throw new InvalidArgumentException('app_secret is required');
            }

            $secret = $optionsRequestType[self::APP_SECRET];

            $signature = new RequestSigner();

            $request = $signature->signRequest($request, $secret);

            $requiredAuthentic = !empty($optionsRequestType[AuthMiddleware::VALIDATED_RESPONSE]);

            if (!$requiredAuthentic) {
                return $handler($request, $options);
            }

            $successCallback = static function (ResponseInterface $response) use ($secret, $signature, $request) {
                if ($response->getStatusCode() !== 401) {
                    if (!$signature->isResponseAuthentic($response, $secret)) {
                        throw new ServerException(
                            'Could not verify the authenticity of the response',
                            $request,
                            $response
                        );
                    }
                }

                return $response;
            };
            $promise = $handler($request, $options);
            \assert($promise instanceof PromiseInterface);

            return $promise->then($successCallback);
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getDefaultHeaderRequest(RequestInterface $request, array $options): RequestInterface
    {
        if (isset($options[self::APP_REQUEST_CONTEXT])) {
            $context = $options[self::APP_REQUEST_CONTEXT];
            if (!$context instanceof Context) {
                throw new InvalidArgumentException('app_request_context must be instance of Context');
            }
            $request = $this->getLanguageHeaderRequest($request, $context);
        }

        if ($request->hasHeader('ct-version')) {
            return clone $request;
        }

        return $request->withAddedHeader('ct-version', $this->contenaVersion);
    }

    private function getLanguageHeaderRequest(RequestInterface $request, Context $context): RequestInterface
    {
        $request = $request->withAddedHeader(self::CONTENA_CONTEXT_LANGUAGE, $context->getLanguageId());

        return $request->withAddedHeader(self::CONTENA_USER_LANGUAGE, $this->localeProvider->getLocaleFromContext($context));
    }
}
