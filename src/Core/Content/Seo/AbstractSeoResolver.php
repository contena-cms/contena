<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    abstract public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl;
}
