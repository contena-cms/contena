<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Hmac;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestSigner
{
    final public const CONTENA_APP_SIGNATURE = 'contena-app-signature';

    final public const CONTENA_INSTALLATION_SIGNATURE = 'contena-installation-signature';

    public function signRequest(RequestInterface $request, string $secret): RequestInterface
    {
        if ($request->getMethod() !== Request::METHOD_POST) {
            return clone $request;
        }

        $body = $request->getBody()->getContents();

        $request->getBody()->rewind();

        if (!\strlen($body)) {
            return clone $request;
        }

        return $request->withAddedHeader(self::CONTENA_INSTALLATION_SIGNATURE, $this->signPayload($body, $secret));
    }

    public function isResponseAuthentic(ResponseInterface $response, string $secret): bool
    {
        if (!$response->hasHeader(self::CONTENA_APP_SIGNATURE)) {
            return false;
        }

        $responseSignature = $response->getHeaderLine(self::CONTENA_APP_SIGNATURE);
        $compareSignature = $this->signPayload($response->getBody()->getContents(), $secret);

        $response->getBody()->rewind();

        return hash_equals($compareSignature, $responseSignature);
    }

    public function signPayload(string $payload, string $secretKey, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secretKey);
    }
}
