<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Contena\Core\Framework\Store\Exception\StoreSessionExpiredException;

/**
 * @internal
 */
class StoreSessionExpiredMiddleware implements MiddlewareInterface
{
    private const STORE_TOKEN_EXPIRED = 'ContenaPlatformException-1';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise->then(function (ResponseInterface $response) use ($request) {
                if ($response->getStatusCode() !== 401) {
                    return $response;
                }

                $body = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
                $code = $body['code'] ?? null;

                if ($code !== self::STORE_TOKEN_EXPIRED) {
                    $response->getBody()->rewind();

                    return $response;
                }

                if ($token = $request->getHeaderLine(StoreRequestOptionsProvider::CONTENA_PLATFORM_TOKEN_HEADER)) {
                    $this->logoutUserByToken($token);
                } else {
                    $this->logoutUserByContext();
                }

                throw new StoreSessionExpiredException();
            });
        };
    }

    private function logoutUserByToken(string $token): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_user SET store_token = NULL WHERE store_token = :token',
            ['token' => $token]
        );
    }

    private function logoutUserByContext(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return;
        }

        $adminUserId = $source->getUserId();
        if (!$adminUserId) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE admin_user SET store_token = NULL WHERE id = :admin_userId',
            ['admin_userId' => Uuid::fromHexToBytes($adminUserId)]
        );
    }
}
