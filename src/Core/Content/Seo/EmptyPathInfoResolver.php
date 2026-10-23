<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ResolvedSeoUrlArray from AbstractSeoResolver
 */
class EmptyPathInfoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractSeoResolver $decorated)
    {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        return $this->decorated;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed in v6.8.0, use {@see resolveUrl()} instead
     *
     * @return ResolvedSeoUrlArray
     */
    public function resolve(string $languageId, string $channelId, string $pathInfo): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::resolveUrl()')
        );

        $resolved = $this->resolveUrl(new SeoUrlRequestContext(
            languageId: $languageId,
            channelId: $channelId,
            pathInfo: $pathInfo,
        ));

        $data = [
            'pathInfo' => $resolved->pathInfo,
            'isCanonical' => $resolved->isCanonical,
        ];

        if ($resolved->id !== null) {
            $data['id'] = $resolved->id;
        }

        if ($resolved->canonicalPathInfo !== null) {
            $data['canonicalPathInfo'] = $resolved->canonicalPathInfo;
        }

        if ($resolved->seoPathInfo !== null) {
            $data['seoPathInfo'] = $resolved->seoPathInfo;
        }

        return $data;
    }

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $seoPathInfo = ltrim($context->pathInfo, '/');
        if ($seoPathInfo === '') {
            return new ResolvedSeoUrl(pathInfo: '/', isCanonical: false);
        }

        return $this->getDecorated()->resolveUrl($context);
    }
}
