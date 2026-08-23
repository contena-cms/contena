<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

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

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $seoPathInfo = ltrim($context->pathInfo, '/');
        if ($seoPathInfo === '') {
            return new ResolvedSeoUrl(pathInfo: '/', isCanonical: false);
        }

        return $this->getDecorated()->resolveUrl($context);
    }
}
