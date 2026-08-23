<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Doctrine\DBAL\Connection;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class ContentSystemPreviewController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentPreviewPayloadStore $payloadStore,
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/content-system/preview/{token}', name: 'frontend.content-system.preview', methods: [Request::METHOD_GET])]
    public function preview(
        string $token,
        Request $request,
        ChannelContext $channelContext,
    ): Response {
        $payloadData = $this->payloadStore->load($token);

        if ($payloadData === null) {
            throw $this->createNotFoundException('Preview token not found or expired.');
        }

        $payload = $this->deserializePayload($payloadData);
        $previewState = $this->previewPageBuilder->build($payload, $channelContext->getContext());
        $resolvedChannelContext = $previewState['channelContext'];
        $themeId = $this->resolveThemeId($resolvedChannelContext->getChannelId());

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $resolvedChannelContext->getChannelId());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $resolvedChannelContext->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $resolvedChannelContext);

        if ($themeId !== null) {
            $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        }

        $response = $this->renderFrontend('@Frontend/frontend/page/content/preview.html.twig', [
            'contentPage' => $previewState['contentPage'],
            'headerParameters' => [],
        ]);

        $frameAncestor = $this->resolveFrameAncestor($request);
        $response->headers->set('Content-Security-Policy', \sprintf('frame-ancestors \'self\' %s;', $frameAncestor));
        // CoreSubscriber defaults to "deny" if this header is missing.
        // We set a non-enforcing value and control embedding via frame-ancestors CSP above.
        $response->headers->set(PlatformRequest::HEADER_FRAME_OPTIONS, 'ALLOWALL');

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function deserializePayload(array $payload): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: \is_array($payload['layout'] ?? null) ? $payload['layout'] : [],
            entityType: (string) ($payload['entityType'] ?? ''),
            entityId: (string) ($payload['entityId'] ?? ''),
            channelId: (string) ($payload['channelId'] ?? ''),
            languageId: $this->nullableString($payload['languageId'] ?? null),
            domainId: $this->nullableString($payload['domainId'] ?? null),
            memberId: $this->nullableString($payload['memberId'] ?? null),
            queryParameters: \is_array($payload['queryParameters'] ?? null) ? $payload['queryParameters'] : [],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function resolveThemeId(string $channelId): ?string
    {
        $themeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`theme_id`)) FROM `theme_channel` WHERE `channel_id` = :channelId ORDER BY `theme_id` LIMIT 1',
            ['channelId' => Uuid::fromHexToBytes($channelId)]
        );

        if (!\is_string($themeId) || !Uuid::isValid($themeId)) {
            return null;
        }

        return $themeId;
    }

    private function resolveFrameAncestor(Request $request): string
    {
        $referer = $request->headers->get('referer');
        if (!\is_string($referer) || $referer === '') {
            return $request->getSchemeAndHttpHost();
        }

        $scheme = parse_url($referer, \PHP_URL_SCHEME);
        $host = parse_url($referer, \PHP_URL_HOST);
        $port = parse_url($referer, \PHP_URL_PORT);

        if (!\is_string($scheme) || !\is_string($host) || $scheme === '' || $host === '') {
            return $request->getSchemeAndHttpHost();
        }

        if (\is_int($port)) {
            return \sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return \sprintf('%s://%s', $scheme, $host);
    }
}
